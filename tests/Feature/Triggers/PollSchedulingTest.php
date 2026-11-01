<?php

use App\Enums\Triggers\SubscriptionStatus;
use App\Enums\Triggers\TriggerEventStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerSubscription;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * A published workflow whose first step is a reader node switched on as a
 * trigger. Push stays off, so the trigger polls on its own schedule.
 *
 * @param  array<string, mixed>  $nodeConfig
 */
function readerTrigger(string $connectorKey, string $nodeType, array $nodeConfig = []): Trigger
{
    Queue::fake([FireTriggerEvent::class]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $connector = Connector::query()->firstWhere('key', $connectorKey) ?? Connector::factory()->create(['key' => $connectorKey]);
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)
        ->create(['data' => ['access_token' => 'token'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    return Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
}

/**
 * A calendar event starting `$minutes` from now.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function eventIn(int $minutes, string $id = 'ev1', array $extra = []): array
{
    return ['id' => $id, 'status' => 'confirmed', 'summary' => 'Standup', 'start' => ['dateTime' => now()->addMinutes($minutes)->toRfc3339String()], ...$extra];
}

afterEach(fn () => Carbon::setTestNow());

describe('waking for the next event', function () {
    it('polls again when the next event comes into its lead time, not on a fixed interval', function () {
        Carbon::setTestNow('2026-03-01 10:00:00');
        $trigger = readerTrigger('google_calendar', 'google_calendar_list_upcoming_events', ['minutes_before' => 10]);
        Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => [eventIn(12)]])]);

        dispatch_sync(new PollTrigger($trigger));

        // 12 minutes to the event, 10 minutes of lead: look again in 2 minutes (the regular interval is far longer).
        expect($trigger->fresh()->next_poll_at->diffInSeconds(now(), true))->toBeGreaterThan(115)->toBeLessThan(125);
    });

    it('starts the flow when the poll wakes inside the lead time, once', function () {
        Carbon::setTestNow('2026-03-01 10:00:00');
        $trigger = readerTrigger('google_calendar', 'google_calendar_list_upcoming_events', ['minutes_before' => 10]);
        $event = eventIn(12);
        Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => [$event]])]);

        dispatch_sync(new PollTrigger($trigger));
        Queue::assertNothingPushed();

        Carbon::setTestNow('2026-03-01 10:02:05');
        dispatch_sync(new PollTrigger($trigger->fresh()));

        $events = $trigger->events()->where('status', TriggerEventStatus::Queued)->get();
        expect($events)->toHaveCount(1)
            ->and($events[0]->delivery_id)->toBe('ev1:'.$event['start']['dateTime'])
            ->and($events[0]->payload['minutes_until_start'])->toBe(10);
        Queue::assertPushed(FireTriggerEvent::class, 1);

        Carbon::setTestNow('2026-03-01 10:05:00');
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', TriggerEventStatus::Queued)->count())->toBe(1);
    });

    it('falls back to the regular interval when nothing is coming up', function () {
        Carbon::setTestNow('2026-03-01 10:00:00');
        $trigger = readerTrigger('google_calendar', 'google_calendar_list_upcoming_events', ['minutes_before' => 10]);
        Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => []])]);

        dispatch_sync(new PollTrigger($trigger));

        expect($trigger->fresh()->next_poll_at->diffInMinutes(now(), true))->toBeGreaterThanOrEqual(14);
    });

    it('does not wake early for an event further off than the regular interval', function () {
        Carbon::setTestNow('2026-03-01 10:00:00');
        $trigger = readerTrigger('google_calendar', 'google_calendar_list_upcoming_events', ['minutes_before' => 10]);
        Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => [eventIn(600)]])]);

        dispatch_sync(new PollTrigger($trigger));

        expect($trigger->fresh()->next_poll_at->diffInMinutes(now(), true))->toBeGreaterThanOrEqual(14)->toBeLessThan(20);
    });

    it('never schedules a poll less than half a minute away', function () {
        Carbon::setTestNow('2026-03-01 10:00:00');
        $trigger = readerTrigger('google_calendar', 'google_calendar_list_upcoming_events', ['minutes_before' => 10]);
        Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => [eventIn(10, extra: ['start' => ['dateTime' => now()->addMinutes(10)->addSeconds(5)->toRfc3339String()]])]])]);

        dispatch_sync(new PollTrigger($trigger));

        expect($trigger->fresh()->next_poll_at->diffInSeconds(now(), true))->toBeGreaterThanOrEqual(29);
    });
});

describe('backing off when nothing happens', function () {
    function backoffTrigger(?SubscriptionStatus $subscription = null): Trigger
    {
        $trigger = new Trigger;

        if ($subscription !== null) {
            $trigger->setRelation('subscription', new TriggerSubscription(['status' => $subscription]));
        }

        return $trigger;
    }

    it('grows the wait by one interval for every few empty polls, up to a cap', function () {
        config(['triggers.adaptive_poll' => ['enabled' => true, 'after_empty_polls' => 6, 'max_multiplier' => 4]]);
        $trigger = backoffTrigger();

        expect(array_map(fn (int $idle): int => $trigger->backoffMultiplier($idle), [0, 5, 6, 11, 12, 18, 24, 500]))
            ->toBe([1, 1, 2, 2, 3, 4, 4, 4]);
    });

    it('never backs off when switched off, or when push is working', function () {
        config(['triggers.adaptive_poll' => ['enabled' => false, 'after_empty_polls' => 6, 'max_multiplier' => 4]]);
        expect(backoffTrigger()->backoffMultiplier(100))->toBe(1);

        config(['triggers.adaptive_poll.enabled' => true]);
        expect(backoffTrigger(SubscriptionStatus::Active)->backoffMultiplier(100))->toBe(1)
            ->and(backoffTrigger(SubscriptionStatus::Degraded)->backoffMultiplier(100))->toBe(4);
    });

    it('counts empty polls, spaces them out, and starts over when something new arrives', function () {
        config(['triggers.adaptive_poll' => ['enabled' => true, 'after_empty_polls' => 2, 'max_multiplier' => 4]]);
        Carbon::setTestNow('2026-03-01 10:00:00');
        $trigger = readerTrigger('google_tasks', 'google_tasks_list_tasks');
        $interval = $trigger->pollIntervalMinutes();
        $items = [];
        Http::fake(['tasks.googleapis.com/*' => function () use (&$items) {
            return Http::response(['items' => $items]);
        }]);

        $gaps = [];

        foreach (range(1, 5) as $poll) {
            dispatch_sync(new PollTrigger($trigger->fresh()));
            $gaps[] = $trigger->fresh()->next_poll_at->diffInMinutes(now(), true);
        }

        // The first poll only takes the baseline, so four of the five were empty polls.
        expect($trigger->fresh()->poll_cursor['idle'])->toBe(4)
            // The first polls wait one interval, the next ones twice that, then three times.
            ->and($gaps[0])->toBeGreaterThanOrEqual($interval - 1)->toBeLessThan($interval * 1.2)
            ->and($gaps[2])->toBeGreaterThanOrEqual($interval * 2 - 1)->toBeLessThan($interval * 2.4)
            ->and($gaps[4])->toBeGreaterThanOrEqual($interval * 3 - 1);

        $items = [['id' => 't1', 'title' => 'New']];
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->fresh()->poll_cursor['idle'])->toBe(0)
            ->and($trigger->fresh()->next_poll_at->diffInMinutes(now(), true))->toBeLessThan($interval * 1.2);
    });
});
