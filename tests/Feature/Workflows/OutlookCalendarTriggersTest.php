<?php

use App\Contracts\SupportsTriggerMode;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Outlook\OutlookListCancelledEventsNode;
use App\Nodes\Integrations\Outlook\OutlookListNewEventsNode;
use App\Nodes\Integrations\Outlook\OutlookListUpcomingEventsNode;
use App\Nodes\Integrations\Outlook\OutlookListUpdatedEventsNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function graphEvent(string $id, array $extra = []): array
{
    return ['id' => $id, 'subject' => "Event {$id}", 'isCancelled' => false, 'isAllDay' => false, 'createdDateTime' => '2026-03-01T09:00:00Z', 'lastModifiedDateTime' => '2026-03-01T09:30:00Z', 'start' => ['dateTime' => '2026-03-01T10:00:00.0000000', 'timeZone' => 'UTC'], ...$extra];
}

afterEach(fn () => Carbon::setTestNow());

it('registers the outlook calendar triggers as trigger nodes', function () {
    $registry = app(NodeRegistry::class);

    foreach (['outlook_list_new_events', 'outlook_list_updated_events', 'outlook_list_cancelled_events', 'outlook_list_upcoming_events'] as $type) {
        expect($registry->has($type))->toBeTrue()
            ->and($registry->resolve($type)->category())->toBe('outlook')
            ->and($registry->resolve($type))->toBeInstanceOf(SupportsTriggerMode::class);
    }
});

it('lists new events newest created first, leaving out cancelled ones', function () {
    Http::fake(['graph.microsoft.com/v1.0/me/events*' => Http::response(['value' => [graphEvent('a'), graphEvent('b', ['isCancelled' => true])]])]);
    $node = new OutlookListNewEventsNode;

    $output = $node->execute(Run::factory()->create(), ['access_token' => 't'], []);

    expect(array_column($output['events'], 'id'))->toBe(['a'])
        ->and($node->triggerListsNewestFirst())->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), '$orderby=createdDateTime desc') && str_starts_with($request->url(), 'https://graph.microsoft.com/v1.0/me/events?'));
});

it('reads one calendar when asked, and refuses an id that could change the path', function () {
    Http::fake(['graph.microsoft.com/*' => Http::response(['value' => []])]);

    (new OutlookListNewEventsNode)->execute(Run::factory()->create(), ['access_token' => 't', 'calendar_id' => 'AAMk_cal-1='], []);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://graph.microsoft.com/v1.0/me/calendars/AAMk_cal-1=/events?'));

    expect(fn () => (new OutlookListNewEventsNode)->execute(Run::factory()->create(), ['access_token' => 't', 'calendar_id' => '../me/messages'], []))
        ->toThrow(InvalidArgumentException::class);
});

it('names an updated event by its id and last change, so each change is an event', function () {
    $node = new OutlookListUpdatedEventsNode;

    expect($node->triggerItemId(graphEvent('a')))->toBe('a:2026-03-01T09:30:00Z')
        ->and($node->triggerItemId(graphEvent('a', ['lastModifiedDateTime' => '2026-03-01T10:00:00Z'])))->not->toBe($node->triggerItemId(graphEvent('a')));
});

it('lists only cancelled events for the cancelled trigger', function () {
    Http::fake(['graph.microsoft.com/v1.0/me/events*' => Http::response(['value' => [graphEvent('a'), graphEvent('b', ['isCancelled' => true])]])]);

    $output = (new OutlookListCancelledEventsNode)->execute(Run::factory()->create(), ['access_token' => 't'], []);

    expect(array_column($output['events'], 'id'))->toBe(['b']);
});

describe('events starting soon', function () {
    function upcomingAt(int $minutes, array $extra = []): array
    {
        return graphEvent('e'.$minutes, ['start' => ['dateTime' => now()->utc()->addMinutes($minutes)->format('Y-m-d\TH:i:s.0000000'), 'timeZone' => 'UTC'], ...$extra]);
    }

    it('starts the flow only inside the lead time, with the minutes left', function () {
        Carbon::setTestNow('2026-03-01 10:00:00');
        $node = new OutlookListUpcomingEventsNode;
        Http::fake(['graph.microsoft.com/v1.0/me/calendarView*' => Http::response(['value' => [
            upcomingAt(8), upcomingAt(40), upcomingAt(5, ['isCancelled' => true]), upcomingAt(6, ['responseStatus' => ['response' => 'declined']]), upcomingAt(7, ['isAllDay' => true]),
        ]])]);

        $output = $node->execute(Run::factory()->create(), ['access_token' => 't', 'minutes_before' => 10], []);
        $due = $node->triggerItems($output, ['minutes_before' => 10]);

        expect(array_column($output['events'], 'id'))->toBe(['e8', 'e40'])
            ->and(array_column($due, 'id'))->toBe(['e8'])
            ->and($node->expandTriggerItem(Run::factory()->create(), [], $due[0])['minutes_until_start'])->toBe(8);
    });

    it('wakes the poll when the next event comes into its lead time, not before', function () {
        Carbon::setTestNow('2026-03-01 10:00:00');
        $node = new OutlookListUpcomingEventsNode;

        $wake = $node->nextTriggerPollAt(['events' => [upcomingAt(8), upcomingAt(90)]], ['minutes_before' => 5]);

        expect($wake->diffInSeconds(now(), true))->toBe(180.0);
        expect($node->nextTriggerPollAt(['events' => [upcomingAt(3)]], ['minutes_before' => 5]))->toBeNull();
    });
});
