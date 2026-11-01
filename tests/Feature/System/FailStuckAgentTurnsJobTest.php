<?php

use App\Enums\RunStatus;
use App\Jobs\System\FailStuckAgentTurnsJob;
use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $this->session = Agent::factory()->forWorkspace($workspace)->create()
        ->sessions()->create(['workspace_id' => $workspace->id]);

    $this->turn = function (string $status, int $startedMinutesAgo) {
        $run = $this->session->runs()->create(['workspace_id' => $this->session->workspace_id, 'trigger_type' => 'manual']);
        $run->forceFill(['status' => $status, 'started_at' => now()->subMinutes($startedMinutesAgo)])->save();

        return $run;
    };
});

it('fails agent turns left running far longer than any turn can take', function () {
    $stuck = ($this->turn)(RunStatus::Running->value, FailStuckAgentTurnsJob::STUCK_AFTER_MINUTES + 1);
    $inFlight = ($this->turn)(RunStatus::Running->value, 2);
    $finished = ($this->turn)(RunStatus::Completed->value, 60);

    app()->call([new FailStuckAgentTurnsJob, 'handle']);

    expect($stuck->fresh()->status)->toBe(RunStatus::Failed);
    expect($stuck->fresh()->error)->toBe('The agent stopped before finishing its reply.');
    expect($inFlight->fresh()->status)->toBe(RunStatus::Running);
    expect($finished->fresh()->status)->toBe(RunStatus::Completed);
});

it('is scheduled on the maintenance queue', function () {
    expect((new FailStuckAgentTurnsJob)->queue)->toBe('system-maintenance');
    $this->artisan('schedule:list')->expectsOutputToContain('FailStuckAgentTurnsJob');
});
