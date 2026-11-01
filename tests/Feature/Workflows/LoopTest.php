<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Agents\WorkspaceAgent;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('runs one child workflow per item and joins the results', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $child = Workflow::factory()->forWorkspace($workspace)->create();
    $child->replaceGraph(['nodes' => [
        ['key' => 'c', 'type' => 'transform', 'config' => ['mapping' => ['n' => 'input.item']]],
    ], 'edges' => []]);
    $child->publishVersion(publisher: $owner);
    $child = $child->fresh();

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [
            ['key' => 'loop', 'type' => 'loop', 'config' => [
                'items_path' => 'input.items',
                'workflow_id' => $child->id,
                'max_concurrent' => 1,
            ]],
            ['key' => 'after', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'loop', 'to' => 'after']],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent, ['items' => [10, 20, 30]]);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);

    $loopNode = $run->nodeRuns->firstWhere('key', 'loop');
    expect($loopNode->status)->toBe(NodeRunStatus::Completed);
    expect($loopNode->output['results'])->toBe([
        0 => ['c' => ['n' => 10]],
        1 => ['c' => ['n' => 20]],
        2 => ['c' => ['n' => 30]],
    ]);
    expect($loopNode->output['errors'])->toBe([]);

    expect(Run::where('parent_node_id', $loopNode->id)->count())->toBe(3);
    expect($run->nodeRuns->firstWhere('key', 'after')->status)->toBe(NodeRunStatus::Completed);
});

it('numbers each loop iteration\'s child run and exposes it via the run resource', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $child = Workflow::factory()->forWorkspace($workspace)->create();
    $child->replaceGraph(['nodes' => [
        ['key' => 'c', 'type' => 'transform', 'config' => ['mapping' => ['n' => 'input.item']]],
    ], 'edges' => []]);
    $child->publishVersion(publisher: $owner);
    $child = $child->fresh();

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [
            ['key' => 'loop', 'type' => 'loop', 'config' => [
                'items_path' => 'input.items',
                'workflow_id' => $child->id,
                'max_concurrent' => 1,
            ]],
        ],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent, ['items' => [10, 20, 30]]);
    $loopNode = $run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'loop');

    expect($loopNode->childRuns->pluck('loop_index')->sort()->values()->all())->toBe([0, 1, 2]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/node-runs/{$loopNode->id}")
        ->assertOk();

    expect(collect($response->json('data.node_run.child_runs'))->pluck('loop_index')->sort()->values()->all())
        ->toBe([0, 1, 2]);
});

it('completes with an empty result set when the items list is empty', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $child = Workflow::factory()->forWorkspace($workspace)->create();
    $child->replaceGraph(['nodes' => [['key' => 'c', 'type' => 'transform', 'config' => ['mapping' => []]]], 'edges' => []]);
    $child->publishVersion(publisher: $owner);
    $child = $child->fresh();

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'loop', 'type' => 'loop', 'config' => ['items_path' => 'input.items', 'workflow_id' => $child->id]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent, ['items' => []]);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'loop')->output)->toBe(['results' => [], 'errors' => []]);
});

it('fails fast on the first item error when on_item_error is fail_fast', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $child = Workflow::factory()->forWorkspace($workspace)->create();
    $child->replaceGraph(['nodes' => [
        ['key' => 'c', 'type' => 'call_api', 'config' => ['method' => 'GET', 'url' => 'http://127.0.0.1:1/unreachable', 'timeout_seconds' => 1]],
    ], 'edges' => []]);
    $child->publishVersion(publisher: $owner);
    $child = $child->fresh();

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'loop', 'type' => 'loop', 'config' => [
            'items_path' => 'input.items',
            'workflow_id' => $child->id,
            'max_concurrent' => 1,
            'on_item_error' => 'fail_fast',
        ]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent, ['items' => [1, 2, 3]]);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);
    $loopNode = $run->nodeRuns->firstWhere('key', 'loop');
    expect($loopNode->status)->toBe(NodeRunStatus::Failed);

    // fail_fast stops releasing further items once max_concurrent=1 serializes
    // execution, only the first item ever actually ran.
    expect(Run::where('parent_node_id', $loopNode->id)->count())->toBe(1);
});

it('tolerates item errors and still completes when on_item_error is continue', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    // A child that fails for every item: asserts "continue" still runs
    // every item and completes the loop (vs. "fail_fast" stopping early).
    $failingChild = Workflow::factory()->forWorkspace($workspace)->create();
    $failingChild->replaceGraph(['nodes' => [
        ['key' => 'c', 'type' => 'call_api', 'config' => ['method' => 'GET', 'url' => 'http://127.0.0.1:1/unreachable', 'timeout_seconds' => 1]],
    ], 'edges' => []]);
    $failingChild->publishVersion(publisher: $owner);
    $failingChild = $failingChild->fresh();

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'loop', 'type' => 'loop', 'config' => [
            'items_path' => 'input.items',
            'workflow_id' => $failingChild->id,
            'max_concurrent' => 1,
            'on_item_error' => 'continue',
        ]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent, ['items' => [1, 2]]);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    $loopNode = $run->nodeRuns->firstWhere('key', 'loop');
    expect($loopNode->status)->toBe(NodeRunStatus::Completed);
    expect(count($loopNode->output['errors']))->toBe(2);

    // continue policy releases every item despite failures.
    expect(Run::where('parent_node_id', $loopNode->id)->count())->toBe(2);
});

it('loops a single node via config._loop without a hand-built child workflow', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [
            ['key' => 'ask', 'type' => 'transform', 'config' => [
                'mapping' => ['n' => 'input.item'],
                '_loop' => ['items_path' => 'input.items'],
            ]],
            ['key' => 'after', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'ask', 'to' => 'after']],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent, ['items' => [10, 20, 30]]);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);

    $loopNode = $run->nodeRuns->firstWhere('key', 'ask');
    expect($loopNode->status)->toBe(NodeRunStatus::Completed);
    expect($loopNode->output['results'])->toBe([
        0 => ['item' => ['n' => 10]],
        1 => ['item' => ['n' => 20]],
        2 => ['item' => ['n' => 30]],
    ]);
    expect(Run::where('parent_node_id', $loopNode->id)->count())->toBe(3);
    expect($run->nodeRuns->firstWhere('key', 'after')->status)->toBe(NodeRunStatus::Completed);
});

it('loops an Agent node via config._loop, matching Gumloop\'s "Loop Mode Support"', function () {
    WorkspaceAgent::fake(['reply']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'ask', 'type' => 'agent', 'config' => [
            'agent_id' => $agent->id,
            'prompt' => 'Echo {{ input.item }}',
            '_loop' => ['items_path' => 'input.items'],
        ]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent, ['items' => ['a', 'b']]);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    $loopNode = $run->nodeRuns->firstWhere('key', 'ask');
    expect($loopNode->status)->toBe(NodeRunStatus::Completed);
    expect(Run::where('parent_node_id', $loopNode->id)->count())->toBe(2);
});

it('cancels items still in flight when fail_fast trips', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    // Routes on the item it was handed: 'hold' parks on a `wait` node (so
    // that item stays in flight), anything else hits an unreachable endpoint
    // and fails.
    $child = Workflow::factory()->forWorkspace($workspace)->create();
    $child->replaceGraph([
        'nodes' => [
            ['key' => 'route', 'type' => 'router', 'config' => ['conditions' => [
                ['path' => 'input.item', 'operator' => 'equals', 'value' => 'hold', 'result' => 'hold'],
            ]]],
            ['key' => 'hold', 'type' => 'wait', 'config' => []],
            ['key' => 'boom', 'type' => 'call_api', 'config' => [
                'method' => 'GET', 'url' => 'http://127.0.0.1:1/unreachable', 'timeout_seconds' => 1,
            ]],
        ],
        'edges' => [
            ['from' => 'route', 'to' => 'hold', 'condition' => 'hold'],
            ['from' => 'route', 'to' => 'boom', 'condition' => 'default'],
        ],
    ]);
    $child->publishVersion(publisher: $owner);
    $child = $child->fresh();

    // The loop node has an error edge, so the *run* survives the loop
    // failing, which is what isolates this to the loop's own cleanup
    // rather than the whole-run teardown `StepFailureHandler` does.
    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [
            ['key' => 'loop', 'type' => 'loop', 'config' => [
                'items_path' => 'input.items',
                'workflow_id' => $child->id,
                'max_concurrent' => 2,
                'on_item_error' => 'fail_fast',
            ]],
            ['key' => 'recovery', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'loop', 'to' => 'recovery', 'condition' => 'error']],
    ]);
    $parent->publishVersion(publisher: $owner);

    // Item 0 parks; item 1 fails and trips fail_fast while item 0 is still
    // in flight.
    $run = app(StartWorkflowRunAction::class)
        ->execute($parent->fresh(), ['items' => ['hold', 'boom']])
        ->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);

    $loopNode = $run->nodeRuns->firstWhere('key', 'loop');
    expect($loopNode->status)->toBe(NodeRunStatus::Failed);
    expect($run->nodeRuns->firstWhere('key', 'recovery')->status)->toBe(NodeRunStatus::Completed);

    $parked = Run::where('parent_node_id', $loopNode->id)->where('loop_index', 0)->sole();

    expect($parked->status)->toBe(RunStatus::Cancelled);
    expect($parked->nodeRuns()->where('key', 'hold')->sole()->callback_token)->toBeNull();
});

it('refuses to loop over a published workflow from another workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherWorkspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Victim']);

    $foreignChild = Workflow::factory()->forWorkspace($otherWorkspace)->create();
    $foreignChild->replaceGraph(['nodes' => [
        ['key' => 'c', 'type' => 'transform', 'config' => ['mapping' => []]],
    ], 'edges' => []]);
    $foreignChild->publishVersion(publisher: $owner);

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'loop', 'type' => 'loop', 'config' => [
            'items_path' => 'input.items',
            'workflow_id' => $foreignChild->id,
        ]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($parent->fresh(), ['items' => [1, 2]])->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);
    expect(Run::where('workflow_id', $foreignChild->id)->exists())->toBeFalse();
});
