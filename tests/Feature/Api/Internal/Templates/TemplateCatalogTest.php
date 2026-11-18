<?php

use App\Models\Templates\AgentTemplate;
use App\Models\Templates\PageTemplate;
use App\Models\Templates\TemplateCollection;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function catalogWorkspace(): array
{
    $owner = User::factory()->create();

    return [app(WorkspaceService::class)->create($owner, ['name' => 'Acme']), $owner];
}

it('lists every template type in one list with its type', function () {
    [$workspace, $owner] = catalogWorkspace();
    WorkflowTemplate::factory()->global()->create(['name' => 'Lead intake']);
    AgentTemplate::factory()->global()->create(['name' => 'Support agent']);
    TemplateCollection::factory()->global()->create(['name' => 'Sales kit']);
    PageTemplate::factory()->global()->create(['name' => 'Contact form']);
    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/templates")->assertOk();

    expect(collect($response->json('data'))->pluck('type', 'name')->all())->toEqual([
        'Contact form' => 'page',
        'Lead intake' => 'workflow',
        'Sales kit' => 'collection',
        'Support agent' => 'agent',
    ]);
    $response->assertJsonPath('meta.total', 4)
        ->assertJsonPath('data.0.type_label', 'Page');
});

it('pages through the list without repeating a template', function () {
    [$workspace, $owner] = catalogWorkspace();
    WorkflowTemplate::factory()->global()->count(4)->create();
    AgentTemplate::factory()->global()->count(3)->create();
    Passport::actingAs($owner);

    $first = $this->getJson("/api/v1/workspaces/{$workspace->id}/templates?per_page=5")->assertOk();
    $second = $this->getJson("/api/v1/workspaces/{$workspace->id}/templates?per_page=5&page=2")->assertOk();

    $first->assertJsonCount(5, 'data')->assertJsonPath('meta.last_page', 2);
    $second->assertJsonCount(2, 'data');
    expect(array_intersect(
        collect($first->json('data'))->pluck('id')->all(),
        collect($second->json('data'))->pluck('id')->all(),
    ))->toBeEmpty();
});

it('lists featured templates first', function () {
    [$workspace, $owner] = catalogWorkspace();
    WorkflowTemplate::factory()->global()->create(['name' => 'Aaa ordinary']);
    AgentTemplate::factory()->global()->create(['name' => 'Zzz featured', 'is_featured' => true]);
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/templates")
        ->assertJsonPath('data.0.name', 'Zzz featured')
        ->assertJsonPath('data.0.is_featured', true);
});

it('filters by type', function () {
    [$workspace, $owner] = catalogWorkspace();
    WorkflowTemplate::factory()->global()->create();
    AgentTemplate::factory()->global()->create(['name' => 'Support agent']);
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/templates?type[]=agent")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Support agent');
});

it('searches names, descriptions and categories without treating % as a wildcard', function () {
    [$workspace, $owner] = catalogWorkspace();
    WorkflowTemplate::factory()->global()->create(['name' => 'Lead intake', 'description' => null, 'category' => null]);
    AgentTemplate::factory()->global()->create(['name' => 'Helper', 'description' => 'Answers every LEAD question', 'category' => null]);
    TemplateCollection::factory()->global()->create(['name' => 'Kit', 'description' => null, 'category' => 'Support']);
    Passport::actingAs($owner);

    $byText = $this->getJson("/api/v1/workspaces/{$workspace->id}/templates?search=lead")->assertOk();
    expect(collect($byText->json('data'))->pluck('name')->sort()->values()->all())->toBe(['Helper', 'Lead intake']);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/templates?search=support")->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/workspaces/{$workspace->id}/templates?search=%25")->assertJsonCount(0, 'data');
});

it('shows the workspace own templates but never another workspace private ones', function () {
    [$workspace, $owner] = catalogWorkspace();
    [$otherWorkspace] = catalogWorkspace();
    WorkflowTemplate::factory()->forWorkspace($workspace)->create(['name' => 'Mine']);
    AgentTemplate::factory()->forWorkspace($otherWorkspace)->create(['name' => 'Theirs']);
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/templates")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Mine')
        ->assertJsonPath('data.0.is_own', true);
});

it('counts the templates inside a collection', function () {
    [$workspace, $owner] = catalogWorkspace();
    $collection = TemplateCollection::factory()->global()->create();
    $collection->items()->create(['templatable_type' => 'workflow_template', 'templatable_id' => WorkflowTemplate::factory()->forWorkspace($workspace)->create()->id, 'position' => 0]);
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/templates?type[]=collection")
        ->assertJsonPath('data.0.items_count', 1);
});

it('rejects an unknown type', function () {
    [$workspace, $owner] = catalogWorkspace();
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/templates?type[]=spreadsheet")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type.0');
});
