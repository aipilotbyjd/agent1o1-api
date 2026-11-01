<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Jobs\Workflows\DispatchNextNodesJob;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\WorkflowRunner;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Bus;

it('reports seconds as its output without blocking execute()', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'd', 'type' => 'delay', 'config' => ['seconds' => 30]],
            ['key' => 'after', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'd', 'to' => 'after']],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'd')->status)->toBe(NodeRunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'd')->output)->toBe(['seconds' => 30]);
});

it('delays the next dispatch job rather than the worker itself', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'd', 'type' => 'delay', 'config' => ['seconds' => 45]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    // Drive execution directly (bypassing StartWorkflowRunAction's own
    // dispatch) so Bus::fake() can capture the *specific* job the delay
    // node's own completion triggers, without also swallowing the initial
    // entry-node dispatch.
    $run = $workflow->runs()->create([
        'workspace_id' => $workflow->workspace_id,
        'runnable_type' => Workflow::class,
        'runnable_id' => $workflow->id,
        'workflow_id' => $workflow->id,
        'workflow_version_id' => $workflow->current_version_id,
        'input' => [],
    ]);
    $nodeRun = $run->nodeRuns()->create(['key' => 'd', 'type' => 'delay']);

    Bus::fake([DispatchNextNodesJob::class]);

    app(WorkflowRunner::class)->executeStep($nodeRun);

    Bus::assertDispatched(DispatchNextNodesJob::class, function (DispatchNextNodesJob $job) {
        return $job->settledNodeRunId !== null && $job->delay !== null;
    });
});
