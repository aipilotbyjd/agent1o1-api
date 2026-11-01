<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarCheckAvailabilityNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarCreateEventNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarDeleteEventNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarGetEventNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarListCalendarsNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarListCancelledEventsNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarListEventsNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarListUpcomingEventsNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarListUpdatedEventsNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarQuickAddEventNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarUpdateEventNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

it('registers every google calendar node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'google_calendar_list_events' => GoogleCalendarListEventsNode::class,
        'google_calendar_create_event' => GoogleCalendarCreateEventNode::class,
        'google_calendar_delete_event' => GoogleCalendarDeleteEventNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('google_calendar');
    }
});

it('lists events defaulting to the primary calendar', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => []])]);

    $node = new GoogleCalendarListEventsNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'ya29-test', 'max_results' => 5], []);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/calendar/v3/calendars/primary/events')
            && $request['maxResults'] == 5;
    });
});

it('creates an event on the given calendar', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['id' => 'e1'])]);

    $node = new GoogleCalendarCreateEventNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'ya29-test',
        'calendar_id' => 'team@example.com',
        'summary' => 'Standup',
        'start_at' => '2026-01-01T09:00:00Z',
        'end_at' => '2026-01-01T09:15:00Z',
    ], []);

    expect($output)->toBe(['id' => 'e1']);
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/calendars/team%40example.com/events')
            && $request['summary'] === 'Standup'
            && $request['start']['dateTime'] === '2026-01-01T09:00:00Z';
    });
});

it('deletes an event by id', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response('', 204)]);

    $node = new GoogleCalendarDeleteEventNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'event_id' => 'e1'], []);

    expect($output)->toBe([]);
    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE' && str_contains($request->url(), '/calendars/primary/events/e1');
    });
});

it('throws when calendar answers a non-2xx status', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['error' => ['message' => 'Not Found']], 404)]);

    $node = new GoogleCalendarDeleteEventNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['access_token' => 'ya29-test', 'event_id' => 'missing'], []))
        ->toThrow(RuntimeException::class, 'Not Found');
});

it('throws when access_token is missing', function () {
    $node = new GoogleCalendarListEventsNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, [], []))->toThrow(RuntimeException::class, 'access_token');
});

const CAL_BASE = ['access_token' => 'ya29-test'];

it('registers the new calendar nodes', function () {
    foreach (['get_event', 'update_event', 'list_calendars', 'quick_add_event', 'check_availability'] as $suffix) {
        expect(app(NodeRegistry::class)->has("google_calendar_{$suffix}"))->toBeTrue();
    }
});

it('creates an event with guests, a location, a time zone and a Meet link', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['id' => 'e2', 'hangoutLink' => 'https://meet.google.com/abc'])]);

    $output = (new GoogleCalendarCreateEventNode)->execute(Run::factory()->create(), [
        ...CAL_BASE,
        'summary' => 'Kickoff',
        'location' => 'Room 4',
        'start_at' => '2026-01-01T09:00:00',
        'end_at' => '2026-01-01T10:00:00',
        'time_zone' => 'Asia/Kolkata',
        'attendees' => 'Ada@Example.com, bob@example.com; ada@example.com',
        'add_meet' => true,
        'send_updates' => 'none',
    ], []);

    expect($output['hangoutLink'])->toBe('https://meet.google.com/abc');
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendUpdates=none')
            && str_contains($request->url(), 'conferenceDataVersion=1')
            && $request['location'] === 'Room 4'
            && $request['start'] === ['dateTime' => '2026-01-01T09:00:00', 'timeZone' => 'Asia/Kolkata']
            && $request['attendees'] === [['email' => 'ada@example.com'], ['email' => 'bob@example.com']]
            && $request['conferenceData']['createRequest']['conferenceSolutionKey']['type'] === 'hangoutsMeet';
    });
});

it('creates an all-day event from dates only and leaves empty fields out', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['id' => 'e3'])]);

    (new GoogleCalendarCreateEventNode)->execute(Run::factory()->create(), [
        ...CAL_BASE, 'summary' => 'Holiday', 'description' => '', 'start_at' => '2026-12-25T00:00:00Z', 'end_at' => '2026-12-26T00:00:00Z', 'all_day' => true,
    ], []);

    Http::assertSent(fn ($request) => $request['start'] === ['date' => '2026-12-25']
        && $request['end'] === ['date' => '2026-12-26']
        && ! isset($request['description'], $request['attendees'], $request['conferenceData'])
        && ! str_contains($request->url(), 'conferenceDataVersion'));
});

it('reads one event', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['id' => 'e1', 'summary' => 'Standup'])]);

    $output = (new GoogleCalendarGetEventNode)->execute(Run::factory()->create(), [...CAL_BASE, 'calendar_id' => 'team@example.com', 'event_id' => 'e1'], []);

    expect($output['summary'])->toBe('Standup');
    Http::assertSent(fn ($request) => $request->method() === 'GET' && str_contains($request->url(), '/calendars/team%40example.com/events/e1'));
});

it('updates only the fields that were filled in', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['id' => 'e1'])]);

    (new GoogleCalendarUpdateEventNode)->execute(Run::factory()->create(), [...CAL_BASE, 'event_id' => 'e1', 'summary' => 'Moved', 'location' => ''], []);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/calendars/primary/events/e1?sendUpdates=all')
        && $request->data() === ['summary' => 'Moved']);
});

it('refuses an update that changes nothing', function () {
    Http::fake();

    (new GoogleCalendarUpdateEventNode)->execute(Run::factory()->create(), [...CAL_BASE, 'event_id' => 'e1'], []);
})->throws(InvalidArgumentException::class, 'at least one field');

it('lists calendars in a flat shape', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => [
        ['id' => 'me@example.com', 'summary' => 'Me', 'primary' => true, 'timeZone' => 'UTC', 'accessRole' => 'owner'],
        ['id' => 'team@group.calendar.google.com', 'summary' => 'Team', 'summaryOverride' => 'My team', 'accessRole' => 'reader'],
    ]])]);

    $output = (new GoogleCalendarListCalendarsNode)->execute(Run::factory()->create(), CAL_BASE, []);

    expect($output['calendars'][0])->toBe(['id' => 'me@example.com', 'name' => 'Me', 'primary' => true, 'time_zone' => 'UTC', 'access_role' => 'owner'])
        ->and($output['calendars'][1]['name'])->toBe('My team');
});

it('quick adds an event from a sentence without a request body', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['id' => 'q1'])]);

    (new GoogleCalendarQuickAddEventNode)->execute(Run::factory()->create(), [...CAL_BASE, 'text' => 'Dentist next Tuesday 3pm'], []);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/calendars/primary/events/quickAdd?text=Dentist+next+Tuesday+3pm')
        && $request->body() === '{}');
});

it('reports busy slots and whether everyone is free', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['calendars' => [
        'primary' => ['busy' => [['start' => '2026-01-01T09:00:00Z', 'end' => '2026-01-01T10:00:00Z']]],
        'bob@example.com' => ['busy' => []],
    ]])]);

    $output = (new GoogleCalendarCheckAvailabilityNode)->execute(Run::factory()->create(), [
        ...CAL_BASE, 'time_min' => '2026-01-01T08:00:00Z', 'time_max' => '2026-01-01T12:00:00Z', 'calendar_ids' => ['primary', 'bob@example.com'],
    ], []);

    expect($output['is_free'])->toBeFalse()
        ->and($output['calendars']['primary']['busy'])->toHaveCount(1);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/freeBusy') && $request['items'] === [['id' => 'primary'], ['id' => 'bob@example.com']]);
});

it('says everyone is free when nothing is busy, and checks the primary calendar by default', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['calendars' => ['primary' => ['busy' => []]]])]);

    $output = (new GoogleCalendarCheckAvailabilityNode)->execute(Run::factory()->create(), [
        ...CAL_BASE, 'time_min' => '2026-01-01T08:00:00Z', 'time_max' => '2026-01-01T12:00:00Z',
    ], []);

    expect($output['is_free'])->toBeTrue();
    Http::assertSent(fn ($request) => $request['items'] === [['id' => 'primary']]);
});

it('tells nobody when deleting an event unless asked', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response('', 204)]);

    (new GoogleCalendarDeleteEventNode)->execute(Run::factory()->create(), [...CAL_BASE, 'event_id' => 'e1', 'send_updates' => 'all'], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendUpdates=all'));
});

it('registers the calendar event triggers', function () {
    foreach (['list_updated_events', 'list_cancelled_events', 'list_upcoming_events'] as $suffix) {
        expect(app(NodeRegistry::class)->has("google_calendar_{$suffix}"))->toBeTrue();
    }
});

it('reads recently changed events for the new event trigger, so one made for next year is not missed', function () {
    Carbon::setTestNow('2026-03-01 10:00:00');
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => []])]);
    $node = new GoogleCalendarListEventsNode;

    $node->execute(Run::factory()->create(), $node->triggerReadConfig(CAL_BASE), []);

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'timeMin')
        && str_contains(urldecode($request->url()), 'updatedMin=2026-03-01T04:00:00+00:00')
        && str_contains($request->url(), 'orderBy=updated')
        && str_contains($request->url(), 'singleEvents=false')
        && str_contains($request->url(), 'maxResults=250'));
    Carbon::setTestNow();
});

it('still lists upcoming events by start time as an action', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => []])]);

    (new GoogleCalendarListEventsNode)->execute(Run::factory()->create(), CAL_BASE, []);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'timeMin') && str_contains($request->url(), 'orderBy=startTime') && str_contains($request->url(), 'singleEvents=true'));
});

it('treats only events made lately as new, and never a cancelled one', function () {
    Carbon::setTestNow('2026-03-01 10:00:00');
    $node = new GoogleCalendarListEventsNode;

    $items = $node->triggerItems(['items' => [
        ['id' => 'fresh', 'created' => '2026-03-01T09:00:00Z'],
        ['id' => 'old-but-edited', 'created' => '2025-01-01T09:00:00Z'],
        ['id' => 'cancelled', 'created' => '2026-03-01T09:00:00Z', 'status' => 'cancelled'],
        ['id' => 'no-created-time'],
    ]], []);

    expect(array_column($items, 'id'))->toBe(['fresh', 'no-created-time']);
    Carbon::setTestNow();
});

it('triggers on an edit but not on the creation of an event, once per edit', function () {
    $node = new GoogleCalendarListUpdatedEventsNode;

    $items = $node->triggerItems(['items' => [
        ['id' => 'just-made', 'created' => '2026-03-01T09:00:00Z', 'updated' => '2026-03-01T09:00:02Z'],
        ['id' => 'moved', 'created' => '2026-03-01T09:00:00Z', 'updated' => '2026-03-01T11:30:00Z'],
        ['id' => 'cancelled', 'status' => 'cancelled', 'created' => '2026-03-01T09:00:00Z', 'updated' => '2026-03-01T11:30:00Z'],
    ]], []);

    expect(array_column($items, 'id'))->toBe(['moved'])
        ->and($node->triggerItemId($items[0]))->toBe('moved:2026-03-01T11:30:00Z')
        ->and($node->triggerName())->toBe('Google Calendar: Event updated');
});

it('asks for changes since the window and leaves out cancelled events when listing updated ones', function () {
    Carbon::setTestNow('2026-03-01 10:00:00');
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => []])]);

    (new GoogleCalendarListUpdatedEventsNode)->execute(Run::factory()->create(), [...CAL_BASE, 'changed_within_minutes' => 30], []);

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'updatedMin=2026-03-01T09:30:00+00:00')
        && ! str_contains($request->url(), 'showDeleted')
        && ! str_contains($request->url(), 'timeMin'));
    Carbon::setTestNow();
});

it('lists cancelled events only, asking Google for deleted ones', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => [
        ['id' => 'gone', 'status' => 'cancelled', 'updated' => '2026-03-01T11:00:00Z'],
        ['id' => 'alive', 'status' => 'confirmed'],
    ]])]);
    $node = new GoogleCalendarListCancelledEventsNode;

    $output = $node->execute(Run::factory()->create(), CAL_BASE, []);

    expect(array_column($output['items'], 'id'))->toBe(['gone'])
        ->and($node->triggerItemId($output['items'][0]))->toBe('gone:cancelled:2026-03-01T11:00:00Z')
        ->and($node->triggerName())->toBe('Google Calendar: Event cancelled');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'showDeleted=true'));
});

describe('event starting soon', function () {
    beforeEach(fn () => Carbon::setTestNow('2026-03-01 10:00:00'));
    afterEach(fn () => Carbon::setTestNow());

    $event = fn (int $minutes, array $extra = []): array => ['id' => 'e'.$minutes, 'status' => 'confirmed', 'start' => ['dateTime' => now()->addMinutes($minutes)->toRfc3339String()], ...$extra];

    it('looks a day ahead and returns only events worth waking for', function () use ($event) {
        Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => [
            $event(30),
            $event(20, ['status' => 'cancelled']),
            ['id' => 'allday', 'status' => 'confirmed', 'start' => ['date' => '2026-03-02']],
            $event(40, ['attendees' => [['self' => true, 'responseStatus' => 'declined']]]),
            $event(50, ['attendees' => [['self' => true, 'responseStatus' => 'accepted']]]),
        ]])]);

        $output = (new GoogleCalendarListUpcomingEventsNode)->execute(Run::factory()->create(), CAL_BASE, []);

        expect(array_column($output['items'], 'id'))->toBe(['e30', 'e50']);
        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'timeMax=2026-03-02T10:00:00+00:00') && str_contains($request->url(), 'singleEvents=true'));
    });

    it('can keep all-day and declined events', function () use ($event) {
        Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => [
            ['id' => 'allday', 'status' => 'confirmed', 'start' => ['date' => '2026-03-02']],
            $event(40, ['attendees' => [['self' => true, 'responseStatus' => 'declined']]]),
        ]])]);

        $output = (new GoogleCalendarListUpcomingEventsNode)->execute(Run::factory()->create(), [...CAL_BASE, 'skip_all_day' => false, 'skip_declined' => false], []);

        expect(array_column($output['items'], 'id'))->toBe(['allday', 'e40']);
    });

    it('starts only the events inside the lead time, including one that began a minute ago', function () use ($event) {
        $node = new GoogleCalendarListUpcomingEventsNode;

        $items = $node->triggerItems(['items' => [$event(-1), $event(5), $event(10), $event(11), $event(300), $event(-10)]], ['minutes_before' => 10]);

        expect(array_column($items, 'id'))->toBe(['e-1', 'e5', 'e10']);
    });

    it('gives each occurrence one id, which changes when it is moved', function () use ($event) {
        $node = new GoogleCalendarListUpcomingEventsNode;

        expect($node->triggerItemId($event(5)))->toBe('e5:'.now()->addMinutes(5)->toRfc3339String())
            ->and($node->triggerItemId(['id' => 'x']))->toBe('x:');
    });

    it('adds how long is left to the payload', function () use ($event) {
        $item = (new GoogleCalendarListUpcomingEventsNode)->expandTriggerItem(Run::factory()->create(), [], $event(7));

        expect($item['minutes_until_start'])->toBe(7)->and($item['id'])->toBe('e7');
    });

    it('wakes at the moment the next event reaches its lead time', function () use ($event) {
        $node = new GoogleCalendarListUpcomingEventsNode;

        $next = $node->nextTriggerPollAt(['items' => [$event(5), $event(25), $event(300)]], ['minutes_before' => 10]);

        // The event in 5 minutes is already inside its lead: the next one to wake for is the one in 25.
        expect($next->equalTo(now()->addMinutes(15)))->toBeTrue();
    });

    it('has nothing to wake for when no event is outside its lead time', function () use ($event) {
        $node = new GoogleCalendarListUpcomingEventsNode;

        expect($node->nextTriggerPollAt(['items' => []], []))->toBeNull()
            ->and($node->nextTriggerPollAt(['items' => [$event(3)]], ['minutes_before' => 10]))->toBeNull();
    });
});
