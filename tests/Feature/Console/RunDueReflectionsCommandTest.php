<?php

use App\Jobs\Agents\RunReflectionJob;
use App\Models\Agents\Agent;
use App\Models\Agents\ReflectionSettings;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Bus;

function reflectionScheduleAgent(): Agent
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return Agent::factory()->forWorkspace($workspace)->create();
}

it('queues a run for an enabled agent whose schedule is due', function () {
    Bus::fake();

    $agent = reflectionScheduleAgent();
    ReflectionSettings::factory()->forAgent($agent)->enabled()->create(['next_run_at' => null]);

    $this->artisan('reflections:run-due')->assertSuccessful();

    Bus::assertDispatched(RunReflectionJob::class, fn (RunReflectionJob $job): bool => $job->agent->is($agent));
});

it('does not queue a disabled agent', function () {
    Bus::fake();

    $agent = reflectionScheduleAgent();
    ReflectionSettings::factory()->forAgent($agent)->create(['is_enabled' => false, 'next_run_at' => null]);

    $this->artisan('reflections:run-due')->assertSuccessful();

    Bus::assertNotDispatched(RunReflectionJob::class);
});

it('does not queue an agent whose next run is still in the future', function () {
    Bus::fake();

    $agent = reflectionScheduleAgent();
    ReflectionSettings::factory()->forAgent($agent)->enabled()->create(['next_run_at' => now()->addDay()]);

    $this->artisan('reflections:run-due')->assertSuccessful();

    Bus::assertNotDispatched(RunReflectionJob::class);
});

it('advances next_run_at after queuing', function () {
    Bus::fake();

    $agent = reflectionScheduleAgent();
    $settings = ReflectionSettings::factory()->forAgent($agent)->enabled()->create([
        'schedule_cron' => '0 22 * * *',
        'next_run_at' => null,
    ]);

    $this->artisan('reflections:run-due');

    expect($settings->fresh()->next_run_at)->not->toBeNull();
});
