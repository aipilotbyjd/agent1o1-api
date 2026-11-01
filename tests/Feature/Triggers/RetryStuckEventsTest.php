<?php

use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;

function makeStuckEvent(TriggerEventStatus $status, int $ageMinutes, array $triggerOverrides = []): TriggerEvent
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create($triggerOverrides);

    $event = $trigger->events()->create([
        'source' => TriggerType::Webhook,
        'status' => $status,
        'payload' => [],
        'delivery_id' => 'd-1',
    ]);

    $event->forceFill([
        'created_at' => now()->subMinutes($ageMinutes),
        'updated_at' => now()->subMinutes($ageMinutes),
    ])->save();

    return $event;
}

it('re-dispatches a queued event stranded past its grace period', function () {
    Queue::fake();
    config(['triggers.stuck.queued_after_minutes' => 10]);

    $event = makeStuckEvent(TriggerEventStatus::Queued, ageMinutes: 20);

    $this->artisan('triggers:retry-stuck')->assertExitCode(0);

    Queue::assertPushed(FireTriggerEvent::class, fn ($job) => $job->event->is($event));
});

it('re-dispatches a running event stranded past its grace period', function () {
    Queue::fake();
    config(['triggers.stuck.running_after_minutes' => 15]);

    $event = makeStuckEvent(TriggerEventStatus::Running, ageMinutes: 30);

    $this->artisan('triggers:retry-stuck');

    Queue::assertPushed(FireTriggerEvent::class, fn ($job) => $job->event->is($event));
});

it('leaves a fresh event alone', function () {
    Queue::fake();

    makeStuckEvent(TriggerEventStatus::Queued, ageMinutes: 1);

    $this->artisan('triggers:retry-stuck');

    Queue::assertNothingPushed();
});

it('skips a stuck event whose trigger has since been deactivated', function () {
    Queue::fake();
    config(['triggers.stuck.queued_after_minutes' => 10]);

    $event = makeStuckEvent(TriggerEventStatus::Queued, ageMinutes: 20, triggerOverrides: ['is_active' => false]);

    $this->artisan('triggers:retry-stuck');

    expect($event->fresh()->status)->toBe(TriggerEventStatus::Skipped);
    Queue::assertNothingPushed();
});
