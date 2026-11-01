<?php

use App\Ai\Agents\WorkflowBuilderAgent;
use App\Exceptions\WorkflowValidationException;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\DryRunner;
use App\Services\Workflows\GraphValidator;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Tools\Request as ToolRequest;

/**
 * @return array{0: WorkflowBuilderSession, 1: Workspace, 2: User}
 */
function flowControlSession(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create(), $workspace, $owner];
}

function publishedChildWorkflow(Workspace $workspace, User $owner, string $name = 'Enrich Lead'): Workflow
{
    $workflow = Workflow::factory()->forWorkspace($workspace)->create(['name' => $name]);
    $workflow->replaceGraph(['nodes' => [['key' => 'c', 'type' => 'transform', 'config' => ['mapping' => []]]], 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);

    return $workflow->fresh();
}

/**
 * @return array<string, mixed>
 */
function flowControlBuilderTools(WorkflowBuilderSession $session): array
{
    return collect(iterator_to_array((new WorkflowBuilderAgent($session))->tools()))
        ->keyBy(fn ($tool) => $tool->name())
        ->all();
}

it('lets the assistant add and wire every flow-control node type', function () {
    [$session, $workspace, $owner] = flowControlSession();
    $child = publishedChildWorkflow($workspace, $owner);
    $tools = flowControlBuilderTools($session);

    $add = fn (string $key, string $type, array $config = []) => (string) $tools['add_node']->handle(
        new ToolRequest(['key' => $key, 'type' => $type, 'config_json' => json_encode((object) $config)], "add-{$key}"),
    );

    expect($add('approve', 'human_approval'))->toBe('Added node [approve].');
    expect($add('wait_for_payment', 'wait', ['timeout_seconds' => 3600, 'continue_on_timeout' => true]))->toBe('Added node [wait_for_payment].');
    expect($add('enrich', 'subflow', ['workflow_id' => $child->id, 'input' => ['source' => 'form']]))->toBe('Added node [enrich].');
    expect($add('each_lead', 'loop', ['items_path' => 'input.leads', 'workflow_id' => $child->id]))->toBe('Added node [each_lead].');
    expect($add('merge', 'join_paths'))->toBe('Added node [merge].');
    expect($add('rejected', 'transform', ['mapping' => []]))->toBe('Added node [rejected].');

    $connect = fn (string $from, string $to, ?string $condition = null) => $tools['connect_nodes']->handle(
        new ToolRequest(array_filter(['from' => $from, 'to' => $to, 'condition' => $condition]), "connect-{$from}-{$to}"),
    );

    $connect('approve', 'wait_for_payment');
    $connect('approve', 'rejected', 'error');
    $connect('wait_for_payment', 'enrich');
    $connect('wait_for_payment', 'each_lead');
    $connect('enrich', 'merge');
    $connect('each_lead', 'merge');

    $graph = $session->fresh()->currentGraph();

    expect($graph['nodes'])->toHaveCount(6);
    expect(app(GraphValidator::class)->validate($graph['nodes'], $graph['edges']))->toBe([]);
});

it('rejects a child workflow the engine could not run', function (Closure $workflowId, string $message) {
    [$session, $workspace, $owner] = flowControlSession();

    $result = (string) flowControlBuilderTools($session)['add_node']->handle(new ToolRequest([
        'key' => 'sub',
        'type' => 'subflow',
        'config_json' => json_encode(['workflow_id' => $workflowId($session, $workspace, $owner)]),
    ], 'call-1'));

    expect($result)->toContain($message);
    expect($session->fresh()->currentGraph()['nodes'])->toBe([]);
})->with([
    'another workspace' => [
        fn ($session, $workspace, $owner) => publishedChildWorkflow(app(WorkspaceService::class)->create($owner, ['name' => 'Other']), $owner)->id,
        'no workflow',
    ],
    'unpublished' => [
        fn ($session, $workspace) => Workflow::factory()->forWorkspace($workspace)->create()->id,
        "isn't published",
    ],
    'not an id' => [
        fn () => 'enrich-lead',
        'no workflow',
    ],
    'itself' => [
        function ($session, $workspace, $owner) {
            $own = publishedChildWorkflow($workspace, $owner, 'Self');
            $session->update(['workflow_id' => $own->id]);

            return $own->id;
        },
        "can't run itself",
    ],
]);

it('checks flow-control configs against their schemas', function () {
    [$session] = flowControlSession();

    $result = (string) flowControlBuilderTools($session)['add_node']->handle(new ToolRequest([
        'key' => 'sub', 'type' => 'subflow', 'config_json' => '{}',
    ], 'call-1'));

    expect($result)->toContain('workflow_id')->toContain('required');
});

it('refuses loop mode on a flow-control node', function () {
    [$session] = flowControlSession();

    $result = (string) flowControlBuilderTools($session)['add_node']->handle(new ToolRequest([
        'key' => 'approve', 'type' => 'human_approval', 'config_json' => json_encode(['_loop' => ['items_path' => 'input.items']]),
    ], 'call-1'));

    expect($result)->toContain("isn't supported on flow-control nodes");
});

it('lists flow-control types and explains how to wire them', function () {
    [$session] = flowControlSession();
    $tools = flowControlBuilderTools($session);

    $types = array_column(json_decode((string) $tools['list_available_nodes']->handle(new ToolRequest([], 'call-1')), true), 'type');
    expect($types)->toContain('human_approval', 'wait', 'subflow', 'loop', 'join_paths', 'transform');

    $schema = json_decode((string) $tools['inspect_node_schema']->handle(new ToolRequest(['type' => 'human_approval'], 'call-2')), true);
    expect($schema['category'])->toBe('flow-logic');
    expect($schema['how_to_wire'])->toContain('"error"');

    // Built-in nodes describe themselves; no wiring note needed.
    expect(json_decode((string) $tools['inspect_node_schema']->handle(new ToolRequest(['type' => 'transform'], 'call-3')), true))
        ->not->toHaveKey('how_to_wire');
});

it('lists only this workspace\'s published, user-facing workflows, minus the one being built', function () {
    [$session, $workspace, $owner] = flowControlSession();
    $enrich = publishedChildWorkflow($workspace, $owner, 'Enrich Lead');
    $own = publishedChildWorkflow($workspace, $owner, 'The One Being Built');
    $session->update(['workflow_id' => $own->id]);
    Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Draft Only']);
    Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Hidden', 'is_internal' => true, 'current_version_id' => $enrich->current_version_id]);
    publishedChildWorkflow(app(WorkspaceService::class)->create($owner, ['name' => 'Other']), $owner, 'Foreign');

    $listed = json_decode((string) flowControlBuilderTools($session)['list_workflows']->handle(new ToolRequest([], 'call-1')), true);

    expect(array_column($listed, 'name'))->toBe(['Enrich Lead']);
    expect($listed[0]['id'])->toBe($enrich->id);
});

it('models flow-control outputs in a dry run', function () {
    $graph = [
        'nodes' => [
            ['key' => 'approve', 'type' => 'human_approval', 'config' => []],
            ['key' => 'after', 'type' => 'run_code', 'config' => ['operations' => [
                ['op' => 'set', 'output' => 'ok', 'value' => '{{ nodes.approve.approved }}'],
                ['op' => 'set', 'output' => 'typo', 'value' => '{{ nodes.approve.aproved }}'],
            ]]],
        ],
        'edges' => [['from' => 'approve', 'to' => 'after', 'condition' => null]],
    ];

    $result = app(DryRunner::class)->run($graph);

    expect($result['warnings'])->toHaveCount(1);
    expect($result['warnings'][0])->toContain('nodes.approve.aproved');
});

it('refuses to publish a subflow with no workflow_id', function () {
    [, $workspace, $owner] = flowControlSession();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'sub', 'type' => 'subflow', 'config' => []]],
        'edges' => [],
    ]);

    expect(fn () => $workflow->fresh()->publishVersion())->toThrow(WorkflowValidationException::class);
});
