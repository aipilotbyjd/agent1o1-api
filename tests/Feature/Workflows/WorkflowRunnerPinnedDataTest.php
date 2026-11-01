<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;

function pinnedNodeWorkflow(): Workflow
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['greeting' => 'input.name']]],
        ],
        'edges' => [],
    ]);
    $workflow->nodes()->first()->forceFill([
        'pinned_data' => ['greeting' => 'pinned'],
        'pinned_at' => now(),
        'pinned_by' => $owner->id,
    ])->save();
    $workflow->publishVersion(publisher: $owner);

    return $workflow->fresh();
}

it('uses pinned data instead of executing the node on a manual run', function () {
    $workflow = pinnedNodeWorkflow();

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['name' => 'Ada'], triggerType: 'manual');
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    $nodeRun = $run->nodeRuns->firstWhere('key', 'a');
    expect($nodeRun->status)->toBe(NodeRunStatus::Completed);
    expect($nodeRun->output)->toBe(['greeting' => 'pinned']);
});

it('ignores pinned data and executes for real on a webhook-triggered run', function () {
    $workflow = pinnedNodeWorkflow();

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['name' => 'Ada'], triggerType: 'webhook');
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    $nodeRun = $run->nodeRuns->firstWhere('key', 'a');
    expect($nodeRun->output)->toBe(['greeting' => 'Ada']);
});
