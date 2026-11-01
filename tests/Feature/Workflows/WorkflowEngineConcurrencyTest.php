<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\Engine\GraphAdvancer;
use App\Services\Workflows\Engine\StepFailureHandler;
use App\Services\Workflows\GraphValidator;
use App\Services\Workspaces\WorkspaceService;

it('lets exactly one of two workers claim the same pending node', function () {
    $nodeRun = NodeRun::factory()->create();
    $first = NodeRun::find($nodeRun->id);
    $second = NodeRun::find($nodeRun->id);

    $claim = ['status' => NodeRunStatus::Running, 'started_at' => now()];

    expect($first->transitionFrom([NodeRunStatus::Pending], $claim))->toBeTrue()
        ->and($second->transitionFrom([NodeRunStatus::Pending], $claim))->toBeFalse()
        ->and($second->status)->toBe(NodeRunStatus::Running);
});

it('does not let a finishing worker overwrite a cancelled node', function () {
    $nodeRun = NodeRun::factory()->create(['status' => NodeRunStatus::Cancelled]);
    $worker = NodeRun::find($nodeRun->id);
    $worker->status = NodeRunStatus::Running;

    $settled = $worker->transitionFrom([NodeRunStatus::Running], ['status' => NodeRunStatus::Completed, 'finished_at' => now()]);

    expect($settled)->toBeFalse()
        ->and($nodeRun->fresh()->status)->toBe(NodeRunStatus::Cancelled);
});

it('keeps a cancelled run cancelled when a node failure arrives afterwards', function () {
    $run = Run::factory()->create(['status' => RunStatus::Cancelled, 'finished_at' => now()]);
    $nodeRun = NodeRun::factory()->forRun($run)->create(['status' => NodeRunStatus::Failed, 'error' => 'boom']);

    app(StepFailureHandler::class)->routeFailureOrFailRun($run, $nodeRun, ['nodes' => [], 'edges' => []]);

    expect($run->fresh()->status)->toBe(RunStatus::Cancelled);
});

it('does not complete a run while a settled node still owes its successors', function () {
    $run = Run::factory()->create(['status' => RunStatus::Running]);
    $nodeRun = NodeRun::factory()->forRun($run)->completed()->create(['pending_advance' => true]);

    app(GraphAdvancer::class)->finishIfDone($run);

    expect($run->fresh()->status)->toBe(RunStatus::Running);

    $nodeRun->forceFill(['pending_advance' => false])->save();
    app(GraphAdvancer::class)->finishIfDone($run);

    expect($run->fresh()->status)->toBe(RunStatus::Completed);
});

it('persists a node\'s retry options on its run row', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'start', 'type' => 'transform', 'config' => ['mapping' => [], '_options' => ['max_attempts' => 3, 'retry_delay_seconds' => 7]]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    $nodeRun = $run->nodeRuns()->where('key', 'start')->sole();

    expect($nodeRun->max_attempts)->toBe(3)
        ->and($nodeRun->retry_delay_seconds)->toBe(7);
});

it('rejects a per-step timeout it cannot enforce', function () {
    $errors = app(GraphValidator::class)->validate(
        [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => [], '_options' => ['timeout_seconds' => 5]]]],
        [],
    );

    expect($errors)->toContain("Node 'a': _options.timeout_seconds is not supported; a running node cannot be interrupted.");
});
