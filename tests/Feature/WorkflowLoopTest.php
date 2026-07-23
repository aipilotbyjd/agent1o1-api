<?php

use App\Engine\WorkflowRunner;
use App\Enums\ExecutionNodeStatus;
use App\Enums\ExecutionStatus;
use App\Models\ExecutionNode;
use App\Models\Run;
use App\Models\Workflow;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->workspace = Workspace::factory()->create();
});

// ── Helper: build a workflow with a given graph inline ────────────────────────

function loopTestWorkflow(Workspace $workspace, array $nodes, array $edges): Workflow
{
    $workflow = Workflow::factory()->active()->create([
        'workspace_id' => $workspace->id,
    ]);

    $version = $workflow->versions()->create([
        'workspace_id' => $workspace->id,
        'version_number' => 1,
        'nodes_data' => $nodes,
        'edges_data' => $edges,
    ]);

    $workflow->update(['current_version_id' => $version->id]);

    return $workflow;
}

// ── Loop body runs once per item ──────────────────────────────────────────────

test('loop node body executes once per item and records per-item node rows', function () {
    Event::fake();

    $workflow = loopTestWorkflow($this->workspace, [
        ['id' => 'trigger', 'type' => 'trigger', 'name' => 'Start', 'config' => []],
        ['id' => 'loop', 'type' => 'loop', 'name' => 'Loop', 'config' => ['items' => ['a', 'b', 'c']]],
        ['id' => 'body', 'type' => 'transform', 'name' => 'Body', 'config' => ['output' => ['item' => '{{ variables.loop_current_item }}']]],
    ], [
        ['source' => 'trigger', 'target' => 'loop'],
        ['source' => 'loop', 'target' => 'body'],
    ]);

    $execution = Run::factory()->create([
        'workflow_id' => $workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);

    app(WorkflowRunner::class)->run($execution);

    $execution->refresh();
    expect($execution->status)->toBe(ExecutionStatus::Completed);

    // One row per loop item for the body node, keyed by loop::body::index
    $bodyRows = ExecutionNode::where('execution_id', $execution->id)
        ->where('node_id', 'body')
        ->get();

    expect($bodyRows)->toHaveCount(3);

    $runKeys = $bodyRows->pluck('node_run_key')->sort()->values()->toArray();
    expect($runKeys)->toBe(['loop::body::0', 'loop::body::1', 'loop::body::2']);
});

test('loop with zero items completes without running body', function () {
    Event::fake();

    $workflow = loopTestWorkflow($this->workspace, [
        ['id' => 'trigger', 'type' => 'trigger', 'name' => 'Start', 'config' => []],
        ['id' => 'loop', 'type' => 'loop', 'name' => 'Loop', 'config' => ['items' => []]],
        ['id' => 'body', 'type' => 'transform', 'name' => 'Body', 'config' => ['output' => []]],
    ], [
        ['source' => 'trigger', 'target' => 'loop'],
        ['source' => 'loop', 'target' => 'body'],
    ]);

    $execution = Run::factory()->create([
        'workflow_id' => $workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);

    app(WorkflowRunner::class)->run($execution);

    expect($execution->fresh()->status)->toBe(ExecutionStatus::Completed);

    // Body node should not have any rows (no items to iterate)
    expect(ExecutionNode::where('execution_id', $execution->id)
        ->where('node_id', 'body')->count())->toBe(0);
});

// ── TryCatch correctly catches a failed node ──────────────────────────────────

test('try-catch routes to catch branch when upstream node fails', function () {
    Event::fake();

    // http_request node to a bad URL will fail; TryCatch should catch it
    // Use a code node that throws explicitly instead of depending on network
    $workflow = loopTestWorkflow($this->workspace, [
        ['id' => 'trigger', 'type' => 'trigger', 'name' => 'Start', 'config' => []],
        ['id' => 'bad', 'type' => 'code', 'name' => 'Bad', 'config' => [
            'language' => 'javascript',
            'code' => 'throw new Error("intentional failure");',
        ]],
        ['id' => 'tc', 'type' => 'try_catch', 'name' => 'TryCatch', 'config' => []],
        ['id' => 'catch_node', 'type' => 'transform', 'name' => 'Caught', 'config' => ['output' => ['caught' => true]]],
        ['id' => 'try_node', 'type' => 'transform', 'name' => 'Success', 'config' => ['output' => ['caught' => false]]],
    ], [
        ['source' => 'trigger', 'target' => 'bad'],
        ['source' => 'bad', 'target' => 'tc'],
        ['source' => 'tc', 'target' => 'catch_node', 'sourceHandle' => 'catch'],
        ['source' => 'tc', 'target' => 'try_node', 'sourceHandle' => 'try'],
    ]);

    $execution = Run::factory()->create([
        'workflow_id' => $workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);

    app(WorkflowRunner::class)->run($execution);

    $execution->refresh();
    expect($execution->status)->toBe(ExecutionStatus::Completed);

    // catch_node should have run; the not-taken try branch is recorded as skipped
    expect(ExecutionNode::where('execution_id', $execution->id)
        ->where('node_id', 'catch_node')->first()->status)->toBe(ExecutionNodeStatus::Completed);

    expect(ExecutionNode::where('execution_id', $execution->id)
        ->where('node_id', 'try_node')->first()?->status)->toBe(ExecutionNodeStatus::Skipped);
});

// ── Checkpoint / resume ───────────────────────────────────────────────────────

test('checkpoint saves suspended node and pending nodes separately', function () {
    // We test the saveCheckpoint data format via the WaitWebhook flow
    // by asserting that after a wait the frontier has the new structure

    Event::fake();
    Queue::fake(); // Prevent ResumeWorkflowJob from running immediately on sync driver

    $workflow = loopTestWorkflow($this->workspace, [
        ['id' => 'trigger', 'type' => 'trigger', 'name' => 'Start', 'config' => []],
        ['id' => 'wait', 'type' => 'wait', 'name' => 'Wait', 'config' => ['timeout_minutes' => 60]],
        ['id' => 'after', 'type' => 'transform', 'name' => 'After', 'config' => ['output' => ['done' => true]]],
    ], [
        ['source' => 'trigger', 'target' => 'wait'],
        ['source' => 'wait', 'target' => 'after'],
    ]);

    $execution = Run::factory()->create([
        'workflow_id' => $workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);

    app(WorkflowRunner::class)->run($execution);

    $execution->refresh();
    expect($execution->status)->toBe(ExecutionStatus::Waiting);

    $checkpoint = $execution->checkpoint;
    expect($checkpoint)->not->toBeNull();

    $frontier = $checkpoint->frontier_snapshot;
    // New format: has 'suspended' key with the wait node id
    expect($frontier)->toHaveKey('suspended')
        ->and($frontier['suspended'])->toBe('wait')
        ->and($frontier['pending'])->toBe([]);
});
