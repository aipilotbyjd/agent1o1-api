<?php

use App\Actions\Workflows\CancelRunAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Events\Runs\RunCancelled;
use App\Exceptions\RunStateException;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Event;

it('cancels a paused run and settles every node run it was waiting on', function () {
    Event::fake([RunCancelled::class]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'gate', 'type' => 'human_approval', 'config' => []],
            ['key' => 'after', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'gate', 'to' => 'after']],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    expect($run->status)->toBe(RunStatus::Running);

    app(CancelRunAction::class)->execute($run, $owner);

    $run = $run->fresh(['nodeRuns']);
    expect($run->status)->toBe(RunStatus::Cancelled);
    expect($run->finished_at)->not->toBeNull();
    expect($run->nodeRuns->firstWhere('key', 'gate')->status)->toBe(NodeRunStatus::Cancelled);

    Event::assertDispatched(RunCancelled::class, fn (RunCancelled $event) => $event->run->id === $run->id
        && $event->cancelledBy?->id === $owner->id);
});

it('clears the callback token so a cancelled wait node can no longer be resolved', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'hold', 'type' => 'wait', 'config' => []]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $token = $run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'hold')->callback_token;
    expect($token)->not->toBeNull();

    app(CancelRunAction::class)->execute($run, $owner);

    $this->postJson("/hooks/wait/{$token}")->assertNotFound();
});

it('cancels the whole tree when a child run is the one cancelled', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $child = Workflow::factory()->forWorkspace($workspace)->create();
    $child->replaceGraph([
        'nodes' => [['key' => 'gate', 'type' => 'human_approval', 'config' => []]],
        'edges' => [],
    ]);
    $child->publishVersion(publisher: $owner);
    $child = $child->fresh();

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'sub', 'type' => 'subflow', 'config' => ['workflow_id' => $child->id]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $parentRun = app(StartWorkflowRunAction::class)->execute($parent);
    $childRun = Run::where('parent_run_id', $parentRun->id)->sole();

    expect($parentRun->fresh()->status)->toBe(RunStatus::Running);

    app(CancelRunAction::class)->execute($childRun, $owner);

    expect($childRun->fresh()->status)->toBe(RunStatus::Cancelled);
    expect($parentRun->fresh()->status)->toBe(RunStatus::Cancelled);
});

it('refuses to cancel a run that already finished', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    expect($run->status)->toBe(RunStatus::Completed);

    app(CancelRunAction::class)->execute($run, $owner);
})->throws(RunStateException::class);
