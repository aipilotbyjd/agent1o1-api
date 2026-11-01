<?php

use App\Enums\Workspaces\Role;
use App\Models\Templates\PageTemplate;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Services\Workflows\Pages\PageSchema;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);

    $this->workflow = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Note writer', 'description' => null]);
    $this->workflow->replaceGraph([
        'nodes' => [
            ['key' => 'topic', 'type' => 'workflow_input', 'config' => ['name' => 'topic', 'label' => 'Topic', 'required' => true], 'position' => ['x' => 0, 'y' => 0]],
            ['key' => 'tone', 'type' => 'workflow_input', 'config' => ['name' => 'tone', 'default' => 'calm'], 'position' => ['x' => 0, 'y' => 100]],
            ['key' => 'note_out', 'type' => 'workflow_output', 'config' => ['name' => 'note', 'value' => '{{ input.topic }}'], 'position' => ['x' => 300, 'y' => 0]],
            ['key' => 'internal_out', 'type' => 'workflow_output', 'config' => ['name' => 'internal', 'value' => 'x'], 'position' => ['x' => 300, 'y' => 100]],
        ],
        'edges' => [
            ['from' => 'topic', 'to' => 'note_out'],
            ['from' => 'tone', 'to' => 'internal_out'],
        ],
    ]);
    $this->workflow->publishVersion(publisher: $this->owner);

    $this->base = "/api/v1/workspaces/{$this->workspace->id}";

    Passport::actingAs($this->owner);
});

/**
 * @param  array<int, array<string, mixed>>  $blocks
 * @param  array<string, mixed>  $theme
 */
function pageTemplateWith(array $blocks, array $theme = [], ?string $workspaceId = null): PageTemplate
{
    $factory = $workspaceId === null ? PageTemplate::factory()->global() : PageTemplate::factory()->state(['workspace_id' => $workspaceId]);

    return $factory->create(['content' => app(PageSchema::class)->normalize(['blocks' => $blocks, 'theme' => $theme])]);
}

/**
 * @param  array<string, mixed>  $props
 * @return array<string, mixed>
 */
function templateBlock(string $type, array $props = [], string $width = 'full'): array
{
    return ['id' => PageSchema::newBlockId(), 'type' => $type, 'width' => $width, 'props' => $props];
}

/**
 * @return array<string, mixed>
 */
function pageFromTemplate(PageTemplate $template, array $attributes = []): array
{
    return test()->postJson(test()->base.'/pages', [
        'workflow_id' => test()->workflow->id,
        'template_id' => $template->id,
        ...$attributes,
    ])->assertCreated()->json('data.page');
}

it('lists a workspace own page templates and global ones but not other workspaces private ones', function () {
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);

    PageTemplate::factory()->forWorkspace($this->workspace)->create(['name' => 'Mine']);
    PageTemplate::factory()->global()->create(['name' => 'Built in']);
    PageTemplate::factory()->forWorkspace($other)->create(['name' => 'Not mine']);

    $names = collect($this->getJson($this->base.'/page-templates')->assertOk()->json('data.page_templates'))->pluck('name');

    expect($names)->toContain('Mine')->toContain('Built in')->not->toContain('Not mine');
});

it('saves a page draft as a template', function () {
    $page = WorkflowPage::factory()->forWorkspace($this->workspace)->create([
        'workflow_id' => $this->workflow->id,
        'content' => app(PageSchema::class)->normalize([
            'blocks' => [templateBlock('heading', ['text' => 'Hello', 'level' => 1])],
            'theme' => ['accent' => '#ff0000'],
        ]),
    ]);

    $template = $this->postJson("{$this->base}/pages/{$page->id}/save-as-template", ['name' => 'Red page', 'category' => 'Brand'])
        ->assertCreated()
        ->json('data.page_template');

    expect($template['name'])->toBe('Red page');
    expect($template['category'])->toBe('Brand');
    expect($template['visibility'])->toBe('private');
    expect($template['source_page_id'])->toBe($page->id);
    expect($template['content'])->toBe($page->content);
});

it('fills a template’s empty slots with the workflow’s inputs and outputs', function () {
    $template = pageTemplateWith([
        templateBlock('heading', ['text' => 'Title', 'level' => 1]),
        templateBlock('text', ['markdown' => '']),
        templateBlock('field', [], 'half'),
        templateBlock('submit', ['label' => 'Go']),
        templateBlock('output', ['display' => 'markdown']),
    ], ['accent' => '#123456', 'font' => 'serif']);

    $page = pageFromTemplate($template, ['name' => 'Notes']);
    $blocks = collect($page['content']['blocks']);

    expect($blocks->pluck('type')->all())->toBe(['heading', 'field', 'field', 'submit', 'output', 'output']);
    expect($blocks->first()['props']['text'])->toBe('Notes');
    expect($blocks->where('type', 'field')->pluck('props.field_key')->all())->toBe(['topic', 'tone']);
    expect($blocks->where('type', 'field')->pluck('width')->unique()->all())->toBe(['half']);
    expect($blocks->where('type', 'output')->pluck('props.output_name')->all())->toBe(['note', 'internal']);
    expect($blocks->where('type', 'output')->first()['props']['display'])->toBe('markdown');
    expect($blocks->firstWhere('type', 'submit')['props']['label'])->toBe('Go');
    expect($page['content']['theme']['accent'])->toBe('#123456');
    expect($page['content']['theme']['font'])->toBe('serif');
    expect($page['issues'])->toBe([]);
    expect($template->fresh()->usage_count)->toBe(1);
});

it('puts the workflow description in an empty text block under the title', function () {
    $this->workflow->update(['description' => 'Writes a short note.']);

    $template = pageTemplateWith([
        templateBlock('heading', ['text' => 'Title', 'level' => 1]),
        templateBlock('text', ['markdown' => '']),
        templateBlock('submit'),
    ]);

    $blocks = collect(pageFromTemplate($template)['content']['blocks']);

    expect($blocks->firstWhere('type', 'text')['props']['markdown'])->toBe('Writes a short note.');
});

it('rebinds slots made for another workflow and drops the ones left over', function () {
    $template = pageTemplateWith([
        templateBlock('field', ['field_key' => 'email', 'label' => 'Your email', 'variant' => 'input']),
        templateBlock('field', ['field_key' => 'topic', 'label' => 'What about?']),
        templateBlock('field', ['field_key' => 'company', 'label' => 'Company']),
        templateBlock('submit'),
        templateBlock('output', ['output_name' => 'summary', 'label' => 'Summary', 'display' => 'table']),
        templateBlock('output', ['output_name' => 'score', 'label' => 'Score']),
        templateBlock('output', ['output_name' => 'extra']),
    ]);

    $fields = collect(pageFromTemplate($template)['content']['blocks'])->where('type', 'field')->values();
    $outputs = collect(pageFromTemplate($template)['content']['blocks'])->where('type', 'output')->values();

    expect($fields->pluck('props.field_key')->all())->toBe(['tone', 'topic']);
    expect($fields[0]['props']['label'])->toBeNull();
    expect($fields[0]['props']['variant'])->toBe('auto');
    expect($fields[1]['props']['label'])->toBe('What about?');

    expect($outputs->pluck('props.output_name')->all())->toBe(['note', 'internal']);
    expect($outputs[0]['props']['label'])->toBeNull();
    expect($outputs[0]['props']['display'])->toBe('auto');
});

it('adds a Run button when the template has none', function () {
    $template = pageTemplateWith([templateBlock('heading', ['text' => 'Title', 'level' => 1])]);

    $types = collect(pageFromTemplate($template)['content']['blocks'])->pluck('type')->all();

    expect($types)->toBe(['heading', 'field', 'field', 'submit', 'output', 'output']);
});

it('404s creating a page from another workspace’s private template', function () {
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $template = pageTemplateWith([], [], $other->id);

    $this->postJson($this->base.'/pages', ['workflow_id' => $this->workflow->id, 'template_id' => $template->id])
        ->assertNotFound();
});

it('renames and deletes a workspace template but not a built-in one', function () {
    $mine = PageTemplate::factory()->forWorkspace($this->workspace)->create();
    $builtIn = PageTemplate::factory()->global()->create();

    $this->patchJson("{$this->base}/page-templates/{$mine->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.page_template.name', 'Renamed');
    $this->deleteJson("{$this->base}/page-templates/{$mine->id}")->assertNoContent();
    $this->deleteJson("{$this->base}/page-templates/{$builtIn->id}")->assertNotFound();

    expect(PageTemplate::query()->find($mine->id))->toBeNull();
    expect(PageTemplate::query()->find($builtIn->id))->not->toBeNull();
});

it('lets a viewer read page templates but not manage them', function () {
    $viewer = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    $template = PageTemplate::factory()->forWorkspace($this->workspace)->create();

    Passport::actingAs($viewer);

    $this->getJson($this->base.'/page-templates')->assertOk();
    $this->patchJson("{$this->base}/page-templates/{$template->id}", ['name' => 'Nope'])->assertForbidden();
    $this->deleteJson("{$this->base}/page-templates/{$template->id}")->assertForbidden();
});

it('seeds built-in layouts that every workflow can use', function () {
    $this->syncSetup('page-templates');

    foreach (PageTemplate::query()->whereNull('workspace_id')->get() as $template) {
        $page = pageFromTemplate($template);

        $blocks = collect(PageSchema::flatten($page['content']['blocks']));

        expect($blocks->where('type', 'field')->pluck('props.field_key')->all())->toBe(['topic', 'tone'], "[{$template->slug}] fields");
        expect($blocks->where('type', 'submit'))->toHaveCount(1, "[{$template->slug}] run button");
        expect($page['issues'])->toBe([]);
    }
});

it('widens a slot left alone on its row when its partner is dropped', function () {
    $template = pageTemplateWith([
        templateBlock('field', [], 'half'),
        templateBlock('field', [], 'half'),
        templateBlock('field', [], 'half'),
        templateBlock('submit'),
        templateBlock('output', [], 'third'),
        templateBlock('output', [], 'third'),
        templateBlock('output', [], 'third'),
    ]);

    $blocks = collect(pageFromTemplate($template)['content']['blocks']);

    expect($blocks->where('type', 'field')->pluck('width')->all())->toBe(['half', 'half']);
    expect($blocks->where('type', 'output')->pluck('width')->all())->toBe(['third', 'two_thirds']);
});

it('gives a single input the full row in a two-column template', function () {
    $this->workflow->replaceGraph([
        'nodes' => [
            ['key' => 'topic', 'type' => 'workflow_input', 'config' => ['name' => 'topic', 'required' => true], 'position' => ['x' => 0, 'y' => 0]],
            ['key' => 'note_out', 'type' => 'workflow_output', 'config' => ['name' => 'note', 'value' => '{{ input.topic }}'], 'position' => ['x' => 300, 'y' => 0]],
        ],
        'edges' => [['from' => 'topic', 'to' => 'note_out']],
    ]);
    $this->workflow->publishVersion(publisher: $this->owner);

    $template = pageTemplateWith([
        templateBlock('field', [], 'half'),
        templateBlock('field', [], 'half'),
        templateBlock('submit'),
        templateBlock('output', [], 'half'),
        templateBlock('output', [], 'half'),
    ]);

    $page = pageFromTemplate($template);
    $blocks = collect($page['content']['blocks']);

    expect($blocks->where('type', 'field')->pluck('width')->all())->toBe(['full']);
    expect($blocks->where('type', 'output')->pluck('width')->all())->toBe(['full']);
    expect($page['issues'])->toBe([]);
});
