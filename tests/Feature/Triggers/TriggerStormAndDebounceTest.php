<?php

use App\Enums\Triggers\TriggerDisabledReason;
use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Exceptions\WorkflowValidationException;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Triggers\TriggerService;
use App\Services\Triggers\TriggerStatus;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

/**
 * A webhook trigger with the given config.
 *
 * @param  array<string, mixed>  $config
 */
function burstTrigger(array $config = []): Trigger
{
    Queue::fake([FireTriggerEvent::class]);
    Cache::flush();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return Trigger::factory()->webhook()->forWorkspace($workspace)->create(['config' => $config]);
}

describe('waiting for quiet (debounce)', function () {
    it('holds events and runs them once nothing new has arrived for the wait', function () {
        $trigger = burstTrigger(['debounce_seconds' => 30]);
        $service = app(TriggerService::class);

        $first = $service->receive($trigger, TriggerType::Webhook, ['n' => 1], 'a');
        $this->travel(20)->seconds();
        $service->receive($trigger, TriggerType::Webhook, ['n' => 2], 'b');

        // 40s after the first, but only 20s after the newest: still waiting.
        $this->travel(20)->seconds();
        expect($first->status)->toBe(TriggerEventStatus::Held)
            ->and($service->heldWindowIsOver($trigger))->toBeFalse();
        Queue::assertNothingPushed();

        $this->travel(11)->seconds();
        expect($service->heldWindowIsOver($trigger))->toBeTrue();

        $combined = $service->flushHeld($trigger);

        expect($combined->payload['count'])->toBe(2)
            ->and($combined->payload['latest'])->toBe(['n' => 2])
            ->and($combined->payload['items'])->toBe([['n' => 1], ['n' => 2]]);
        Queue::assertPushed(FireTriggerEvent::class, 1);
    });

    it('runs anyway once the oldest event has waited the longest a trigger may be held', function () {
        config(['triggers.debounce.max_wait_seconds' => 120]);
        $trigger = burstTrigger(['debounce_seconds' => 30]);
        $service = app(TriggerService::class);

        // A source that never goes quiet: an event every 20 seconds.
        foreach (range(1, 7) as $n) {
            $service->receive($trigger, TriggerType::Webhook, ['n' => $n], "e{$n}");
            $this->travel(20)->seconds();
        }

        expect($service->heldWindowIsOver($trigger))->toBeTrue();
    });

    it('is flushed by the scheduled command only when the quiet period is over', function () {
        $trigger = burstTrigger(['debounce_seconds' => 60]);
        app(TriggerService::class)->receive($trigger, TriggerType::Webhook, ['n' => 1], 'a');

        $this->travel(30)->seconds();
        $this->artisan('triggers:flush-batches')->assertSuccessful();
        expect($trigger->events()->where('status', TriggerEventStatus::Held)->count())->toBe(1);

        $this->travel(31)->seconds();
        $this->artisan('triggers:flush-batches')->assertSuccessful();
        expect($trigger->events()->where('status', TriggerEventStatus::Held)->count())->toBe(0)
            ->and($trigger->events()->where('status', TriggerEventStatus::Queued)->count())->toBe(1);
    });

    it('never holds a Run now', function () {
        $trigger = burstTrigger(['debounce_seconds' => 60]);

        $event = app(TriggerService::class)->receive($trigger, TriggerType::Manual, ['n' => 1]);

        expect($event->status)->toBe(TriggerEventStatus::Queued);
    });

    it('flushes what is held at once when the trigger stops waiting', function () {
        $trigger = burstTrigger(['debounce_seconds' => 60]);
        app(TriggerService::class)->receive($trigger, TriggerType::Webhook, ['n' => 1], 'a');

        $trigger->update(['config' => []]);

        expect(app(TriggerService::class)->heldWindowIsOver($trigger->fresh()))->toBeTrue();
    });

    it('refuses to publish a trigger that both groups and waits', function () {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();

        $workflow->replaceGraph(['nodes' => [['key' => 'hook', 'type' => 'webhook_trigger', 'config' => ['batch_seconds' => 30, 'debounce_seconds' => 30]]], 'edges' => []]);

        expect(fn () => $workflow->publishVersion(publisher: $owner))->toThrow(WorkflowValidationException::class);
    });

    it('carries the wait from a published trigger node onto its row', function () {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();

        $workflow->replaceGraph(['nodes' => [['key' => 'hook', 'type' => 'webhook_trigger', 'config' => ['debounce_seconds' => 45, 'storm_limit' => 50]]], 'edges' => []]);
        $workflow->publishVersion(publisher: $owner);

        $trigger = Trigger::query()->where('target_id', $workflow->id)->sole();

        expect($trigger->config['debounce_seconds'])->toBe(45)
            ->and($trigger->config['storm_limit'])->toBe(50);
    });
});

describe('storm detector', function () {
    it('switches a trigger off when too many events arrive in a minute, and says why', function () {
        config(['triggers.storm.max_events_per_minute' => 3]);
        $trigger = burstTrigger();
        $service = app(TriggerService::class);

        foreach (range(1, 3) as $n) {
            expect($service->receive($trigger, TriggerType::Webhook, ['n' => $n], "e{$n}")->status)->toBe(TriggerEventStatus::Queued);
        }

        $tripped = $service->receive($trigger, TriggerType::Webhook, ['n' => 4], 'e4');

        expect($tripped->status)->toBe(TriggerEventStatus::Skipped)
            ->and($tripped->error)->toContain('more than 3 events')
            ->and($trigger->fresh()->is_active)->toBeFalse()
            ->and($trigger->fresh()->disabled_reason)->toBe(TriggerDisabledReason::Storm->value);
        Queue::assertPushed(FireTriggerEvent::class, 3);

        // The rest of the flood is dropped at the door.
        expect($service->receive($trigger->fresh(), TriggerType::Webhook, ['n' => 5], 'e5')->error)->toBe('Trigger is inactive.');
        expect(app(TriggerStatus::class)->for($trigger->fresh())['state'])->toBe('off_storm');
    });

    it('uses the trigger\'s own limit, not below ten', function () {
        $trigger = burstTrigger(['storm_limit' => 1]);

        expect($trigger->stormLimit())->toBe(10);

        $service = app(TriggerService::class);

        foreach (range(1, 10) as $n) {
            $service->receive($trigger, TriggerType::Webhook, ['n' => $n], "e{$n}");
        }

        expect($trigger->fresh()->is_active)->toBeTrue();

        $service->receive($trigger, TriggerType::Webhook, ['n' => 11], 'e11');

        expect($trigger->fresh()->is_active)->toBeFalse();
    });

    it('starts counting afresh each minute', function () {
        config(['triggers.storm.max_events_per_minute' => 3]);
        $trigger = burstTrigger();
        $service = app(TriggerService::class);

        foreach (range(1, 3) as $n) {
            $service->receive($trigger, TriggerType::Webhook, ['n' => $n], "a{$n}");
        }

        $this->travel(61)->seconds();

        foreach (range(1, 3) as $n) {
            $service->receive($trigger, TriggerType::Webhook, ['n' => $n], "b{$n}");
        }

        expect($trigger->fresh()->is_active)->toBeTrue();
    });

    it('does not count Run now, held events or filtered events', function () {
        config(['triggers.storm.max_events_per_minute' => 2]);
        $service = app(TriggerService::class);

        $manual = burstTrigger();
        foreach (range(1, 5) as $n) {
            $service->receive($manual, TriggerType::Manual, ['n' => $n]);
        }

        $grouping = burstTrigger(['batch_seconds' => 60]);
        foreach (range(1, 5) as $n) {
            $service->receive($grouping, TriggerType::Webhook, ['n' => $n], "g{$n}");
        }

        $filtered = burstTrigger(['filters' => [['path' => 'action', 'operator' => 'equals', 'value' => 'opened']]]);
        foreach (range(1, 5) as $n) {
            $service->receive($filtered, TriggerType::Webhook, ['action' => 'closed'], "f{$n}");
        }

        expect($manual->fresh()->is_active)->toBeTrue()
            ->and($grouping->fresh()->is_active)->toBeTrue()
            ->and($filtered->fresh()->is_active)->toBeTrue();
    });

    it('can be switched off', function () {
        config(['triggers.storm.enabled' => false, 'triggers.storm.max_events_per_minute' => 1]);
        $trigger = burstTrigger();

        foreach (range(1, 5) as $n) {
            app(TriggerService::class)->receive($trigger, TriggerType::Webhook, ['n' => $n], "e{$n}");
        }

        expect($trigger->fresh()->is_active)->toBeTrue();
    });

    it('forgets why it was off once switched back on', function () {
        config(['triggers.storm.max_events_per_minute' => 1]);
        $trigger = burstTrigger();
        $service = app(TriggerService::class);

        $service->receive($trigger, TriggerType::Webhook, ['n' => 1], 'a');
        $service->receive($trigger, TriggerType::Webhook, ['n' => 2], 'b');
        expect($trigger->fresh()->disabled_reason)->toBe('storm');

        $service->update($trigger->fresh(), ['is_active' => true]);

        expect($trigger->fresh()->is_active)->toBeTrue()
            ->and($trigger->fresh()->disabled_reason)->toBeNull();
    });
});
