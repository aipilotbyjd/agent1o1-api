<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Exceptions\WorkflowValidationException;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Event;

function publishedWorkflow(array $nodes, array $edges): Workflow
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => $nodes, 'edges' => $edges]);
    $workflow->publishVersion(publisher: $owner);

    return $workflow->fresh();
}

it('runs a linear graph end to end and fires RunCompleted', function () {
    Event::fake([RunCompleted::class]);

    $workflow = publishedWorkflow(
        [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['greeting' => 'input.name']]],
            ['key' => 'b', 'type' => 'run_code', 'config' => ['operations' => [
                ['op' => 'copy', 'output' => 'shouted', 'path' => 'nodes.a.greeting'],
            ]]],
        ],
        [['from' => 'a', 'to' => 'b']],
    );

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['name' => 'Ada']);

    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns)->toHaveCount(2);
    expect($run->nodeRuns->firstWhere('key', 'a')->output)->toBe(['greeting' => 'Ada']);
    expect($run->nodeRuns->firstWhere('key', 'b')->output)->toBe(['shouted' => 'Ada']);
    expect($run->output)->toBe(['a' => ['greeting' => 'Ada'], 'b' => ['shouted' => 'Ada']]);

    Event::assertDispatched(RunCompleted::class, fn ($event) => $event->run->id === $run->id);
});

it('branches on a router result and skips the non-matching branch', function () {
    $workflow = publishedWorkflow(
        [
            ['key' => 'route', 'type' => 'router', 'config' => ['conditions' => [
                ['path' => 'input.status', 'operator' => 'equals', 'value' => 'open', 'result' => 'is_open'],
            ]]],
            ['key' => 'open_path', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'closed_path', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        [
            ['from' => 'route', 'to' => 'open_path', 'condition' => 'is_open'],
            ['from' => 'route', 'to' => 'closed_path', 'condition' => 'default'],
        ],
    );

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['status' => 'open']);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'open_path')->status)->toBe(NodeRunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'closed_path')->status)->toBe(NodeRunStatus::Skipped);
});

it('routes through an error edge when a node fails', function () {
    $workflow = publishedWorkflow(
        [
            ['key' => 'a', 'type' => 'call_api', 'config' => ['method' => 'GET', 'url' => 'http://127.0.0.1:1/unreachable', 'timeout_seconds' => 1]],
            ['key' => 'recovery', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'happy_path', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        [
            ['from' => 'a', 'to' => 'recovery', 'condition' => 'error'],
            ['from' => 'a', 'to' => 'happy_path'],
        ],
    );

    $run = app(StartWorkflowRunAction::class)->execute($workflow, []);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'a')->status)->toBe(NodeRunStatus::Failed);
    expect($run->nodeRuns->firstWhere('key', 'recovery')->status)->toBe(NodeRunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'happy_path')->status)->toBe(NodeRunStatus::Skipped);
});

it('fails the whole run when a node fails with no error edge and no continue_on_error', function () {
    Event::fake([RunFailed::class]);

    $workflow = publishedWorkflow(
        [
            ['key' => 'a', 'type' => 'call_api', 'config' => ['method' => 'GET', 'url' => 'http://127.0.0.1:1/unreachable', 'timeout_seconds' => 1]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        [['from' => 'a', 'to' => 'b']],
    );

    $run = app(StartWorkflowRunAction::class)->execute($workflow, []);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->error)->not->toBeNull();
    expect($run->nodeRuns->firstWhere('key', 'a')->status)->toBe(NodeRunStatus::Failed);

    Event::assertDispatched(RunFailed::class, fn ($event) => $event->run->id === $run->id);
});

it('retries a failing node up to max_attempts before giving up', function () {
    $workflow = publishedWorkflow(
        [
            [
                'key' => 'a',
                'type' => 'call_api',
                'config' => [
                    'method' => 'GET',
                    'url' => 'http://127.0.0.1:1/unreachable',
                    'timeout_seconds' => 1,
                    '_options' => ['max_attempts' => 3, 'retry_delay_seconds' => 0],
                ],
            ],
        ],
        [],
    );

    $run = app(StartWorkflowRunAction::class)->execute($workflow, []);
    $run = $run->fresh(['nodeRuns']);

    $nodeRun = $run->nodeRuns->firstWhere('key', 'a');

    expect($nodeRun->attempt)->toBe(3);
    expect($nodeRun->status)->toBe(NodeRunStatus::Failed);
    expect($run->status)->toBe(RunStatus::Failed);
});

it('continues past a failed node when continue_on_error is set', function () {
    $workflow = publishedWorkflow(
        [
            [
                'key' => 'a',
                'type' => 'call_api',
                'config' => [
                    'method' => 'GET',
                    'url' => 'http://127.0.0.1:1/unreachable',
                    'timeout_seconds' => 1,
                    '_options' => ['continue_on_error' => true],
                ],
            ],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        [['from' => 'a', 'to' => 'b']],
    );

    $run = app(StartWorkflowRunAction::class)->execute($workflow, []);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'a')->status)->toBe(NodeRunStatus::Failed);
    expect($run->nodeRuns->firstWhere('key', 'b')->status)->toBe(NodeRunStatus::Completed);
});

it('refuses to start a run for an unpublished workflow', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    app(StartWorkflowRunAction::class)->execute($workflow);
})->throws(WorkflowValidationException::class);
