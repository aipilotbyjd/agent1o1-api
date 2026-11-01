<?php

use App\Models\Templates\AgentTemplate;
use App\Models\Templates\TemplateCollection;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForTemplateCollection(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('creates a template collection', function () {
    [$workspace, $owner] = ownerWorkspaceForTemplateCollection();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/template-collections", [
        'name' => 'Customer Support Pack',
    ]);

    $response->assertCreated();
    expect($response->json('data.template_collection.name'))->toBe('Customer Support Pack');
});

it('adds a workflow template and an agent template to a collection', function () {
    [$workspace, $owner] = ownerWorkspaceForTemplateCollection();
    $collection = TemplateCollection::factory()->forWorkspace($workspace)->create();
    $workflowTemplate = WorkflowTemplate::factory()->forWorkspace($workspace)->create();
    $agentTemplate = AgentTemplate::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/template-collections/{$collection->id}/items", [
        'templatable_type' => 'workflow_template',
        'templatable_id' => $workflowTemplate->id,
    ])->assertCreated();

    $this->postJson("/api/v1/workspaces/{$workspace->id}/template-collections/{$collection->id}/items", [
        'templatable_type' => 'agent_template',
        'templatable_id' => $agentTemplate->id,
    ])->assertCreated();

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/template-collections/{$collection->id}");

    $response->assertOk();
    expect($response->json('data.template_collection.items'))->toHaveCount(2);
});

it('rejects adding a template that is not visible to the workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForTemplateCollection();
    [$otherWorkspace] = ownerWorkspaceForTemplateCollection();
    $collection = TemplateCollection::factory()->forWorkspace($workspace)->create();
    $foreignTemplate = WorkflowTemplate::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/template-collections/{$collection->id}/items", [
        'templatable_type' => 'workflow_template',
        'templatable_id' => $foreignTemplate->id,
    ])->assertStatus(422);
});

it('bulk instantiates every template in a collection', function () {
    [$workspace, $owner] = ownerWorkspaceForTemplateCollection();
    $collection = TemplateCollection::factory()->forWorkspace($workspace)->create();
    $workflowTemplate = WorkflowTemplate::factory()->forWorkspace($workspace)->create([
        'graph' => ['nodes' => [], 'edges' => []],
    ]);
    $agentTemplate = AgentTemplate::factory()->forWorkspace($workspace)->create();

    $collection->items()->create(['templatable_type' => 'workflow_template', 'templatable_id' => $workflowTemplate->id]);
    $collection->items()->create(['templatable_type' => 'agent_template', 'templatable_id' => $agentTemplate->id]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/template-collections/{$collection->id}/use");

    $response->assertCreated();
    expect($response->json('data.workflows'))->toHaveCount(1);
    expect($response->json('data.agents'))->toHaveCount(1);
    expect($workflowTemplate->fresh()->usage_count)->toBe(1);
    expect($agentTemplate->fresh()->usage_count)->toBe(1);
});

it('reorders collection items', function () {
    [$workspace, $owner] = ownerWorkspaceForTemplateCollection();
    $collection = TemplateCollection::factory()->forWorkspace($workspace)->create();
    $workflowTemplate = WorkflowTemplate::factory()->forWorkspace($workspace)->create();
    $item = $collection->items()->create(['templatable_type' => 'workflow_template', 'templatable_id' => $workflowTemplate->id, 'position' => 0]);

    Passport::actingAs($owner);

    $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/template-collections/{$collection->id}/items/reorder", [
        'items' => [['id' => $item->id, 'position' => 5]],
    ]);

    $response->assertOk();
    expect($item->fresh()->position)->toBe(5);
});

it('removes an item from a collection', function () {
    [$workspace, $owner] = ownerWorkspaceForTemplateCollection();
    $collection = TemplateCollection::factory()->forWorkspace($workspace)->create();
    $workflowTemplate = WorkflowTemplate::factory()->forWorkspace($workspace)->create();
    $item = $collection->items()->create(['templatable_type' => 'workflow_template', 'templatable_id' => $workflowTemplate->id]);

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/template-collections/{$collection->id}/items/{$item->id}")
        ->assertNoContent();

    expect($collection->items()->count())->toBe(0);
});
