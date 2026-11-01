<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;

/**
 * A run that *fails* has to abandon its in-flight work exactly as a
 * cancelled one does: see `RunCanceller`. Settling only `pending`/`running`
 * nodes left the two parked states (`awaiting_approval`, `awaiting_callback`)
 * live on a dead run, and left `subflow`/`loop` child runs executing for a
 * parent node that had already settled.
 */
const UNREACHABLE = ['method' => 'GET', 'url' => 'http://127.0.0.1:1/unreachable', 'timeout_seconds' => 1];

function workspaceOwner(): array
{
    $owner = User::factory()->create();

    return [$owner, app(WorkspaceService::class)->create($owner, ['name' => 'Acme'])];
}

/**
 * A graph that forks from an entry node into a parked branch and a branch
 * that always fails, so the run fails while the parked node is still live.
 *
 * @param  array<string, mixed>  $parkedNode
 */
function forkedFailingWorkflow(Workflow $workflow, array $parkedNode, ?User $publisher = null): Workflow
{
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => 'transform', 'config' => ['mapping' => []]],
            $parkedNode,
            ['key' => 'boom', 'type' => 'call_api', 'config' => UNREACHABLE],
        ],
        'edges' => [
            ['from' => 'start', 'to' => $parkedNode['key']],
            ['from' => 'start', 'to' => 'boom'],
        ],
    ]);
    $workflow->publishVersion(publisher: $publisher);

    return $workflow->fresh();
}

it('cancels a node parked on approval when another branch fails the run', function () {
    [$owner, $workspace] = workspaceOwner();

    $workflow = forkedFailingWorkflow(
        Workflow::factory()->forWorkspace($workspace)->create(),
        ['key' => 'gate', 'type' => 'human_approval', 'config' => []],
        $owner,
    );

    $run = app(StartWorkflowRunAction::class)->execute($workflow)->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->nodeRuns->firstWhere('key', 'gate')->status)->toBe(NodeRunStatus::Cancelled);
    expect($run->nodeRuns->firstWhere('key', 'gate')->finished_at)->not->toBeNull();
});

it('clears a parked wait node callback token when another branch fails the run', function () {
    [$owner, $workspace] = workspaceOwner();

    $workflow = forkedFailingWorkflow(
        Workflow::factory()->forWorkspace($workspace)->create(),
        ['key' => 'hold', 'type' => 'wait', 'config' => []],
        $owner,
    );

    $run = app(StartWorkflowRunAction::class)->execute($workflow)->fresh(['nodeRuns']);

    $hold = $run->nodeRuns->firstWhere('key', 'hold');

    expect($run->status)->toBe(RunStatus::Failed);
    expect($hold->status)->toBe(NodeRunStatus::Cancelled);
    expect($hold->callback_token)->toBeNull();
});

it('leaves the node that caused the failure with its own failed status and error', function () {
    [$owner, $workspace] = workspaceOwner();

    $workflow = forkedFailingWorkflow(
        Workflow::factory()->forWorkspace($workspace)->create(),
        ['key' => 'gate', 'type' => 'human_approval', 'config' => []],
        $owner,
    );

    $run = app(StartWorkflowRunAction::class)->execute($workflow)->fresh(['nodeRuns']);

    $boom = $run->nodeRuns->firstWhere('key', 'boom');

    expect($boom->status)->toBe(NodeRunStatus::Failed);
    expect($boom->error)->not->toBeNull();
});

it('cancels an in-flight sub-workflow child run when the parent run fails', function () {
    [$owner, $workspace] = workspaceOwner();

    // The child parks on a `wait` node, so it is still in flight when the
    // parent's other branch fails the run.
    $child = Workflow::factory()->forWorkspace($workspace)->create();
    $child->replaceGraph([
        'nodes' => [['key' => 'hold', 'type' => 'wait', 'config' => []]],
        'edges' => [],
    ]);
    $child->publishVersion(publisher: $owner);
    $child = $child->fresh();

    $parent = forkedFailingWorkflow(
        Workflow::factory()->forWorkspace($workspace)->create(),
        ['key' => 'sub', 'type' => 'subflow', 'config' => ['workflow_id' => $child->id]],
        $owner,
    );

    $run = app(StartWorkflowRunAction::class)->execute($parent)->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);

    $childRun = Run::where('parent_run_id', $run->id)->sole();

    expect($childRun->status)->toBe(RunStatus::Cancelled);
    expect($childRun->nodeRuns()->where('key', 'hold')->sole()->callback_token)->toBeNull();
});
