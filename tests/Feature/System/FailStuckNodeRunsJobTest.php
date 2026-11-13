<?php

use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Jobs\System\FailStuckNodeRunsJob;
use App\Jobs\Workflows\ExecuteNodeJob;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->stuckNode = function (array $options = [], string $type = 'transform', int $startedMinutesAgo = FailStuckNodeRunsJob::STUCK_AFTER_MINUTES + 1): NodeRun {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $workflow->replaceGraph([
            'nodes' => [['key' => 'start', 'type' => 'transform', 'config' => ['mapping' => [], '_options' => $options]]],
            'edges' => [],
        ]);
        $workflow->publishVersion(publisher: $owner);

        $run = Run::factory()->forWorkflow($workflow->fresh())->create(['status' => RunStatus::Running, 'started_at' => now()->subHour()]);

        return NodeRun::factory()->forRun($run)->create([
            'key' => 'start',
            'type' => $type,
            'status' => NodeRunStatus::Running,
            'started_at' => now()->subMinutes($startedMinutesAgo),
            'max_attempts' => $options['max_attempts'] ?? 1,
        ]);
    };
});

it('fails a step whose worker was lost, and the run with it', function () {
    $nodeRun = ($this->stuckNode)();

    app()->call([new FailStuckNodeRunsJob, 'handle']);

    expect($nodeRun->fresh()->status)->toBe(NodeRunStatus::Failed)
        ->and($nodeRun->fresh()->error)->toContain('worker was lost')
        ->and($nodeRun->run->fresh()->status)->toBe(RunStatus::Failed);
});

it('retries a lost step that is configured for more attempts instead of failing the run', function () {
    $nodeRun = ($this->stuckNode)(['max_attempts' => 2, 'retry_delay_seconds' => 5]);

    app()->call([new FailStuckNodeRunsJob, 'handle']);

    expect($nodeRun->fresh()->status)->toBe(NodeRunStatus::Pending)
        ->and($nodeRun->fresh()->attempt)->toBe(2)
        ->and($nodeRun->run->fresh()->status)->toBe(RunStatus::Running);
    Queue::assertPushed(ExecuteNodeJob::class, fn (ExecuteNodeJob $job): bool => $job->nodeRunId === $nodeRun->id);
});

it('leaves a step that is still within the time a worker can legitimately take', function () {
    $nodeRun = ($this->stuckNode)(startedMinutesAgo: 2);

    app()->call([new FailStuckNodeRunsJob, 'handle']);

    expect($nodeRun->fresh()->status)->toBe(NodeRunStatus::Running)
        ->and($nodeRun->run->fresh()->status)->toBe(RunStatus::Running);
});

it('leaves loop and subflow steps alone, since they run as long as their child runs', function (string $type) {
    $nodeRun = ($this->stuckNode)(type: $type);

    app()->call([new FailStuckNodeRunsJob, 'handle']);

    expect($nodeRun->fresh()->status)->toBe(NodeRunStatus::Running)
        ->and($nodeRun->run->fresh()->status)->toBe(RunStatus::Running);
})->with(['loop', 'subflow']);

it('does not touch a step whose run has already been cancelled', function () {
    $nodeRun = ($this->stuckNode)();
    $nodeRun->run->forceFill(['status' => RunStatus::Cancelled, 'finished_at' => now()])->save();

    app()->call([new FailStuckNodeRunsJob, 'handle']);

    expect($nodeRun->fresh()->status)->toBe(NodeRunStatus::Running)
        ->and($nodeRun->run->fresh()->status)->toBe(RunStatus::Cancelled);
});

it('is scheduled on the maintenance queue', function () {
    expect((new FailStuckNodeRunsJob)->queue)->toBe('system-maintenance');
    $this->artisan('schedule:list')->expectsOutputToContain('FailStuckNodeRunsJob');
});
