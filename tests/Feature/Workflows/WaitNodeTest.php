<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Jobs\System\ExpireStaleWaitsJob;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\WorkflowRunner;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function waitWorkflow(array $waitConfig = []): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'w', 'type' => 'wait', 'config' => $waitConfig],
            ['key' => 'after', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'w', 'to' => 'after']],
    ]);
    $workflow->publishVersion(publisher: $owner);

    return [$workflow->fresh(), $owner];
}

it('pauses the run with a one-time callback token', function () {
    [$workflow] = waitWorkflow();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $waitNode = $run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'w');

    expect($waitNode->status)->toBe(NodeRunStatus::AwaitingCallback);
    expect($waitNode->callback_token)->not->toBeNull();
});

it('resumes via the callback and completes the run', function () {
    [$workflow] = waitWorkflow();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $token = $run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'w')->callback_token;

    app(WorkflowRunner::class)->resolveCallback($token, ['approved' => true]);

    $run = $run->fresh(['nodeRuns']);
    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'w')->output)->toBe(['approved' => true]);
    expect($run->nodeRuns->firstWhere('key', 'w')->callback_token)->toBeNull();
});

it('404s a replayed callback since the token is single-use', function () {
    [$workflow] = waitWorkflow();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $token = $run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'w')->callback_token;

    app(WorkflowRunner::class)->resolveCallback($token, []);

    app(WorkflowRunner::class)->resolveCallback($token, []);
})->throws(ModelNotFoundException::class);

it('fails the run when a wait expires with no continue_on_timeout', function () {
    [$workflow] = waitWorkflow(['timeout_seconds' => 1]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $waitNode = $run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'w');
    $waitNode->forceFill(['callback_expires_at' => now()->subMinute()])->save();

    (new ExpireStaleWaitsJob)->handle(app(WorkflowRunner::class));

    $run = $run->fresh(['nodeRuns']);
    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->nodeRuns->firstWhere('key', 'w')->status)->toBe(NodeRunStatus::Failed);
});

it('completes with timed_out output when continue_on_timeout is set', function () {
    [$workflow] = waitWorkflow(['timeout_seconds' => 1, 'continue_on_timeout' => true]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $waitNode = $run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'w');
    $waitNode->forceFill(['callback_expires_at' => now()->subMinute()])->save();

    (new ExpireStaleWaitsJob)->handle(app(WorkflowRunner::class));

    $run = $run->fresh(['nodeRuns']);
    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'w')->output)->toBe(['timed_out' => true]);
    expect($run->nodeRuns->firstWhere('key', 'after')->status)->toBe(NodeRunStatus::Completed);
});
