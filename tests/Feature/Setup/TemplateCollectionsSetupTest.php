<?php

use App\Models\Templates\AgentTemplate;
use App\Models\Templates\TemplateCollection;
use App\Models\Templates\TemplateCollectionItem;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Services\Setup\Datasets\TemplateCollectionsDataset;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->syncSetup('template-collections');
});

it('ships a starter kit for each business function, each public and global', function () {
    $collections = TemplateCollection::query()->whereNull('workspace_id')->get();
    $definitions = require database_path('setup/template-collections.php');

    expect($collections)->toHaveCount(count($definitions))
        ->and($collections->every(fn (TemplateCollection $collection): bool => $collection->isPublic()))->toBeTrue()
        ->and($collections->pluck('category')->unique()->count())->toBeGreaterThanOrEqual(10);
});

it('puts every template a kit names in it, in the order the file gives', function () {
    foreach (require database_path('setup/template-collections.php') as $slug => $definition) {
        $collection = TemplateCollection::query()->whereNull('workspace_id')->where('slug', $slug)->firstOrFail();
        $items = $collection->items()->with('templatable')->get();

        expect($items)->toHaveCount(count($definition['items']), "[{$slug}] item count")
            ->and($items->pluck('position')->all())->toBe(range(0, count($definition['items']) - 1));

        foreach ($definition['items'] as $index => [$kind, $templateSlug]) {
            $template = $items[$index]->templatable;

            expect($template)->toBeInstanceOf($kind === 'workflow' ? WorkflowTemplate::class : AgentTemplate::class)
                ->and($template->slug)->toBe($templateSlug)
                ->and($template->workspace_id)->toBeNull();
        }
    }
});

it('gives every kit both workflows to run the process and agents to help around it', function () {
    foreach (TemplateCollection::query()->whereNull('workspace_id')->with('items')->get() as $collection) {
        $types = $collection->items->pluck('templatable_type')->unique()->all();

        expect(in_array('workflow_template', $types, true))->toBeTrue("[{$collection->slug}] has no workflow")
            ->and(in_array('agent_template', $types, true))->toBeTrue("[{$collection->slug}] has no agent");
    }
});

it('does not duplicate a kit or its items when synced again', function () {
    $collections = TemplateCollection::query()->count();
    $items = TemplateCollectionItem::query()->count();

    $this->syncSetup('template-collections');

    expect(TemplateCollection::query()->count())->toBe($collections)
        ->and(TemplateCollectionItem::query()->count())->toBe($items);
});

it('puts a kit back the way the file says if its items were changed', function () {
    $collection = TemplateCollection::query()->where('slug', 'sales-starter-kit')->firstOrFail();
    $expected = $collection->items()->count();
    $stray = WorkflowTemplate::query()->whereNull('workspace_id')->where('slug', 'weekly-team-update')->firstOrFail();

    TemplateCollectionItem::query()->create(['collection_id' => $collection->id, 'templatable_type' => $stray->getMorphClass(), 'templatable_id' => $stray->id, 'position' => 99]);
    $collection->items()->first()->delete();
    $collection->delete();

    $this->syncSetup('template-collections');

    $collection = TemplateCollection::query()->where('slug', 'sales-starter-kit')->firstOrFail();

    expect($collection->trashed())->toBeFalse()
        ->and($collection->items()->count())->toBe($expected)
        ->and($collection->items()->where('templatable_id', $stray->id)->exists())->toBeFalse();
});

it('refuses a kit that names a template that does not exist, lists one twice, is too small or is missing its details', function () {
    $good = ['name' => 'N', 'description' => 'D', 'category' => 'C'];

    $dataset = new class extends TemplateCollectionsDataset
    {
        /** @var array<string, array<string, mixed>> */
        public array $override = [];

        protected function collections(): array
        {
            return $this->override;
        }
    };

    $dataset->override = [
        'ghost' => [...$good, 'items' => [['workflow', 'no-such-workflow'], ['agent', 'no-such-agent']]],
        'twice' => [...$good, 'items' => [['workflow', 'weekly-team-update'], ['workflow', 'weekly-team-update']]],
        'tiny' => [...$good, 'items' => [['workflow', 'weekly-team-update']]],
        'odd' => [...$good, 'items' => [['page', 'simple-form'], ['agent', 'okr-coach']]],
        'bare' => ['items' => [['workflow', 'weekly-team-update'], ['agent', 'okr-coach']]],
    ];

    $problems = implode("\n", $dataset->problems());

    expect($problems)->toContain('names the workflow template [no-such-workflow], which does not exist')
        ->and($problems)->toContain('names the agent template [no-such-agent], which does not exist')
        ->and($problems)->toContain('lists the workflow template [weekly-team-update] twice')
        ->and($problems)->toContain('Collection [tiny] needs between 2 and 12 templates, not 1')
        ->and($problems)->toContain('item of kind [page]')
        ->and($problems)->toContain('Collection [bare] has no name')
        ->and($problems)->toContain('Collection [bare] has no description');
});

it('has no problems in the shipped kits', function () {
    expect(app(TemplateCollectionsDataset::class)->problems())->toBe([]);
});

it('lists the kits to any workspace and shows what is in one', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Passport::actingAs($owner);

    $listed = collect($this->getJson("/api/v1/workspaces/{$workspace->id}/template-collections")->assertOk()->json('data.template_collections'));
    $kit = $listed->firstWhere('slug', 'customer-support-desk');

    expect($kit['name'])->toBe('Customer support desk');

    $shown = $this->getJson("/api/v1/workspaces/{$workspace->id}/template-collections/{$kit['id']}")->assertOk()->json('data.template_collection.items');

    expect($shown)->toHaveCount(7)
        ->and($shown[0]['templatable']['slug'])->toBe('ticket-classify-and-route');
});

it('installs every kit into a workspace, one workflow or agent per item', function () {
    $this->syncSetup('workflow-templates');
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Passport::actingAs($owner);

    foreach (TemplateCollection::query()->whereNull('workspace_id')->with('items')->get() as $collection) {
        $workflows = $collection->items->where('templatable_type', 'workflow_template')->count();
        $agents = $collection->items->where('templatable_type', 'agent_template')->count();

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/template-collections/{$collection->id}/use")->assertCreated();

        expect($response->json('data.workflows'))->toHaveCount($workflows, "[{$collection->slug}] workflows")
            ->and($response->json('data.agents'))->toHaveCount($agents, "[{$collection->slug}] agents");
    }
});
