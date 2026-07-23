<?php

use App\Engine\WorkflowRunner;
use App\Enums\ExecutionStatus;
use App\Models\ExecutionNode;
use App\Models\Run;
use App\Models\Workflow;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->workspace = Workspace::factory()->create();
});

function branchWorkflow(Workspace $workspace, array $nodes, array $edges): Workflow
{
    $workflow = Workflow::factory()->active()->create(['workspace_id' => $workspace->id]);

    $version = $workflow->versions()->create([
        'workspace_id' => $workspace->id,
        'version_number' => 1,
        'nodes_data' => $nodes,
        'edges_data' => $edges,
    ]);

    $workflow->update(['current_version_id' => $version->id]);

    return $workflow;
}

function runBranchWorkflow(Workspace $workspace, array $nodes, array $edges): Run
{
    $workflow = branchWorkflow($workspace, $nodes, $edges);

    $execution = Run::factory()->create([
        'workflow_id' => $workflow->id,
        'workspace_id' => $workspace->id,
    ]);

    app(WorkflowRunner::class)->run($execution);

    return $execution->refresh();
}

/** @return array<string, string> node_id => status */
function nodeStatuses(Run $execution): array
{
    return ExecutionNode::where('execution_id', $execution->id)
        ->get()
        ->mapWithKeys(fn ($n) => [$n->node_id => $n->status->value])
        ->toArray();
}

// if / true → merge continues; the not-taken branch is skipped, not stranded ──────

test('a merge after a condition still runs on the taken (true) branch', function () {
    Event::fake();

    $execution = runBranchWorkflow($this->workspace, [
        ['id' => 'trigger', 'type' => 'trigger', 'name' => 'Start', 'config' => []],
        ['id' => 'gate', 'type' => 'condition', 'name' => 'Gate', 'config' => [
            'mode' => 'if',
            'conditions' => [['left' => 1, 'operator' => '==', 'right' => 1]],
        ]],
        ['id' => 'passT', 'type' => 'transform', 'name' => 'Pass', 'config' => ['output' => ['branch' => 'true']]],
        ['id' => 'failT', 'type' => 'transform', 'name' => 'Fail', 'config' => ['output' => ['branch' => 'false']]],
        ['id' => 'merge', 'type' => 'merge', 'name' => 'Merge', 'config' => ['mode' => 'merge']],
        ['id' => 'final', 'type' => 'transform', 'name' => 'Final', 'config' => ['output' => ['done' => true]]],
    ], [
        ['source' => 'trigger', 'target' => 'gate'],
        ['source' => 'gate', 'target' => 'passT', 'sourceHandle' => 'true'],
        ['source' => 'gate', 'target' => 'failT', 'sourceHandle' => 'false'],
        ['source' => 'passT', 'target' => 'merge'],
        ['source' => 'failT', 'target' => 'merge'],
        ['source' => 'merge', 'target' => 'final'],
    ]);

    expect($execution->status)->toBe(ExecutionStatus::Completed);

    $statuses = nodeStatuses($execution);
    expect($statuses)->toMatchArray([
        'passT' => 'completed',
        'failT' => 'skipped',
        'merge' => 'completed',
        'final' => 'completed',
    ]);

    // merge saw only the taken branch's payload
    $merge = ExecutionNode::where('execution_id', $execution->id)->where('node_id', 'merge')->first();
    expect($merge->output_data)->toBe(['branch' => 'true']);
});

// The symmetric case: false branch taken, true branch skipped ─────────────────────

test('a merge after a condition still runs on the taken (false) branch', function () {
    Event::fake();

    $execution = runBranchWorkflow($this->workspace, [
        ['id' => 'trigger', 'type' => 'trigger', 'name' => 'Start', 'config' => []],
        ['id' => 'gate', 'type' => 'condition', 'name' => 'Gate', 'config' => [
            'mode' => 'if',
            'conditions' => [['left' => 1, 'operator' => '==', 'right' => 2]],
        ]],
        ['id' => 'passT', 'type' => 'transform', 'name' => 'Pass', 'config' => ['output' => ['branch' => 'true']]],
        ['id' => 'failT', 'type' => 'transform', 'name' => 'Fail', 'config' => ['output' => ['branch' => 'false']]],
        ['id' => 'merge', 'type' => 'merge', 'name' => 'Merge', 'config' => ['mode' => 'merge']],
        ['id' => 'final', 'type' => 'transform', 'name' => 'Final', 'config' => ['output' => ['done' => true]]],
    ], [
        ['source' => 'trigger', 'target' => 'gate'],
        ['source' => 'gate', 'target' => 'passT', 'sourceHandle' => 'true'],
        ['source' => 'gate', 'target' => 'failT', 'sourceHandle' => 'false'],
        ['source' => 'passT', 'target' => 'merge'],
        ['source' => 'failT', 'target' => 'merge'],
        ['source' => 'merge', 'target' => 'final'],
    ]);

    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect(nodeStatuses($execution))->toMatchArray([
        'passT' => 'skipped',
        'failT' => 'completed',
        'merge' => 'completed',
        'final' => 'completed',
    ]);

    $merge = ExecutionNode::where('execution_id', $execution->id)->where('node_id', 'merge')->first();
    expect($merge->output_data)->toBe(['branch' => 'false']);
});

// A merge whose every input comes from a not-taken branch is itself skipped, and
// the skip cascades past it. ──────────────────────────────────────────────────────

test('skip cascades through a merge when all of its inputs are skipped', function () {
    Event::fake();

    $execution = runBranchWorkflow($this->workspace, [
        ['id' => 'trigger', 'type' => 'trigger', 'name' => 'Start', 'config' => []],
        ['id' => 'gate', 'type' => 'condition', 'name' => 'Gate', 'config' => [
            'mode' => 'if',
            'conditions' => [['left' => 1, 'operator' => '==', 'right' => 1]],
        ]],
        // taken branch
        ['id' => 'taken', 'type' => 'transform', 'name' => 'Taken', 'config' => ['output' => ['ok' => true]]],
        // not-taken subtree: two nodes → their own merge → a tail node
        ['id' => 'b1', 'type' => 'transform', 'name' => 'B1', 'config' => ['output' => ['b' => 1]]],
        ['id' => 'b2', 'type' => 'transform', 'name' => 'B2', 'config' => ['output' => ['b' => 2]]],
        ['id' => 'subMerge', 'type' => 'merge', 'name' => 'SubMerge', 'config' => ['mode' => 'merge']],
        ['id' => 'tail', 'type' => 'transform', 'name' => 'Tail', 'config' => ['output' => ['tail' => true]]],
    ], [
        ['source' => 'trigger', 'target' => 'gate'],
        ['source' => 'gate', 'target' => 'taken', 'sourceHandle' => 'true'],
        ['source' => 'gate', 'target' => 'b1', 'sourceHandle' => 'false'],
        ['source' => 'gate', 'target' => 'b2', 'sourceHandle' => 'false'],
        ['source' => 'b1', 'target' => 'subMerge'],
        ['source' => 'b2', 'target' => 'subMerge'],
        ['source' => 'subMerge', 'target' => 'tail'],
    ]);

    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect(nodeStatuses($execution))->toMatchArray([
        'taken' => 'completed',
        'b1' => 'skipped',
        'b2' => 'skipped',
        'subMerge' => 'skipped',
        'tail' => 'skipped',
    ]);
});

// Regression: an ordinary parallel fan-out/fan-in (no condition) still joins once
// with both inputs — the activation tracking must not skip a legitimately-fed join.

test('a parallel fan-out still joins with all inputs', function () {
    Event::fake();

    $execution = runBranchWorkflow($this->workspace, [
        ['id' => 'trigger', 'type' => 'trigger', 'name' => 'Start', 'config' => []],
        ['id' => 'a', 'type' => 'transform', 'name' => 'A', 'config' => ['output' => ['a' => 1]]],
        ['id' => 'b', 'type' => 'transform', 'name' => 'B', 'config' => ['output' => ['b' => 2]]],
        ['id' => 'merge', 'type' => 'merge', 'name' => 'Merge', 'config' => ['mode' => 'merge']],
    ], [
        ['source' => 'trigger', 'target' => 'a'],
        ['source' => 'trigger', 'target' => 'b'],
        ['source' => 'a', 'target' => 'merge'],
        ['source' => 'b', 'target' => 'merge'],
    ]);

    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect(nodeStatuses($execution))->toMatchArray([
        'a' => 'completed',
        'b' => 'completed',
        'merge' => 'completed',
    ]);

    // merge combined both parents' outputs
    $merge = ExecutionNode::where('execution_id', $execution->id)->where('node_id', 'merge')->first();
    expect($merge->output_data)->toBe(['a' => 1, 'b' => 2]);
});
