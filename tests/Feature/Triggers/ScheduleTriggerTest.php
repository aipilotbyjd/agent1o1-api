<?php

use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

function makeScheduleTrigger(string $cron): Trigger
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return Trigger::factory()->schedule($cron)->forWorkspace($workspace)->create();
}

it('queues an event for a due schedule trigger', function () {
    $trigger = makeScheduleTrigger('* * * * *');

    $this->artisan('triggers:run-due');

    expect($trigger->events()->count())->toBe(1);
});

it('queues nothing for a non-due schedule', function () {
    $farFuture = now()->addYear();
    $trigger = makeScheduleTrigger("{$farFuture->minute} {$farFuture->hour} 1 1 *");

    $this->artisan('triggers:run-due');

    expect($trigger->events()->count())->toBe(0);
});

it('produces exactly one row when the due command runs twice in the same minute', function () {
    $trigger = makeScheduleTrigger('* * * * *');

    $this->artisan('triggers:run-due');
    $this->artisan('triggers:run-due');

    expect($trigger->events()->count())->toBe(1);
});

it('keeps firing other schedules when one has an invalid stored cron expression', function () {
    $broken = makeScheduleTrigger('not a cron');
    $healthy = makeScheduleTrigger('* * * * *');

    $this->artisan('triggers:run-due')->assertSuccessful();

    expect($broken->events()->count())->toBe(0)
        ->and($healthy->events()->count())->toBe(1);
});

it('fires a one-time schedule once and then switches it off', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $trigger = Trigger::factory()->schedule()->forWorkspace($workspace)->create([
        'config' => ['run_once_at' => now()->addMinute()->toIso8601String()],
    ]);

    expect($trigger->next_run_at)->not->toBeNull();

    $this->travelTo(now()->addMinute());
    $this->artisan('triggers:run-due');
    $this->artisan('triggers:run-due');

    expect($trigger->events()->count())->toBe(1)
        ->and($trigger->fresh()->is_active)->toBeFalse();
});

it('gives a one-time schedule in the past no next run', function () {
    expect(Trigger::nextRunFor(['run_once_at' => now()->subHour()->toIso8601String()], now(), true))->toBeNull();
});

it('reads a one-time date with no offset in the schedule timezone', function () {
    $next = Trigger::nextRunFor(['run_once_at' => '2099-01-01 09:00:00', 'timezone' => 'America/New_York'], now());

    expect($next->toIso8601String())->toBe('2099-01-01T14:00:00+00:00');
});
