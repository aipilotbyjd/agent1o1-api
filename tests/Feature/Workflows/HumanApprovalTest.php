<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\WorkflowRunner;
use App\Services\Workspaces\WorkspaceService;

function approvalWorkflow(array $edges = [['from' => 'gate', 'to' => 'after']]): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'gate', 'type' => 'human_approval', 'config' => []],
            ['key' => 'after', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => $edges,
    ]);
    $workflow->publishVersion(publisher: $owner);

    return [$workflow->fresh(), $owner];
}

it('pauses the run awaiting approval', function () {
    [$workflow] = approvalWorkflow();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Running);
    $gate = $run->nodeRuns->firstWhere('key', 'gate');
    expect($gate->status)->toBe(NodeRunStatus::AwaitingApproval);
    expect($gate->approval)->not->toBeNull();
});

it('resumes and completes the run when the approval is approved', function () {
    [$workflow, $owner] = approvalWorkflow();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $run = $run->fresh(['nodeRuns']);
    $approval = $run->nodeRuns->firstWhere('key', 'gate')->approval;

    app(WorkflowRunner::class)->resolveApproval($approval, true, $owner, 'looks good');

    $run = $run->fresh(['nodeRuns']);
    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'after')->status)->toBe(NodeRunStatus::Completed);
    expect($approval->fresh()->decision)->toBe('approved');
});

it('fails the run when the approval is rejected with no error edge', function () {
    [$workflow, $owner] = approvalWorkflow();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $run = $run->fresh(['nodeRuns']);
    $approval = $run->nodeRuns->firstWhere('key', 'gate')->approval;

    app(WorkflowRunner::class)->resolveApproval($approval, false, $owner, 'no');

    $run = $run->fresh(['nodeRuns']);
    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->nodeRuns->firstWhere('key', 'gate')->status)->toBe(NodeRunStatus::Failed);
    expect($approval->fresh()->decision)->toBe('rejected');
});

it('routes through an error edge when the approval is rejected', function () {
    [$workflow, $owner] = approvalWorkflow([
        ['from' => 'gate', 'to' => 'after', 'condition' => 'error'],
    ]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $run = $run->fresh(['nodeRuns']);
    $approval = $run->nodeRuns->firstWhere('key', 'gate')->approval;

    app(WorkflowRunner::class)->resolveApproval($approval, false, $owner);

    $run = $run->fresh(['nodeRuns']);
    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'after')->status)->toBe(NodeRunStatus::Completed);
});

it('ignores a second decision on an already-decided approval', function () {
    [$workflow, $owner] = approvalWorkflow();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $approval = $run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'gate')->approval;

    app(WorkflowRunner::class)->resolveApproval($approval, true, $owner);
    app(WorkflowRunner::class)->resolveApproval($approval, false, $owner);

    expect($approval->fresh()->decision)->toBe('approved');
});
