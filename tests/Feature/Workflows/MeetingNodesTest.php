<?php

use App\Contracts\SupportsTriggerMode;
use App\Enums\NodeRunStatus;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Nothing here may reach Zoom, Calendly, Cal.com or Microsoft for real: an unfaked call fails the test.
    Http::preventStrayRequests();
});

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function meetingOutput(string $type, array $config): array
{
    $nodeRun = runNodeStep($type, ['access_token' => 'test-token', ...$config]);

    expect($nodeRun->status)->toBe(NodeRunStatus::Completed, (string) $nodeRun->error);

    return $nodeRun->output;
}

function meetingRequest(string $needle, ?string $method = null): Request
{
    $found = collect(Http::recorded())->map(fn (array $pair): Request => $pair[0])->first(fn (Request $request): bool => str_contains($request->url(), $needle) && ($method === null || $request->method() === $method));

    expect($found)->not->toBeNull("Nothing was sent to {$needle}");

    return $found;
}

/* --------------------------------------------------------------------- Zoom */

it('creates a Zoom meeting at the time written, in the zone written, sent as UTC', function () {
    Http::fake(['api.zoom.us/v2/users/me/meetings' => Http::response(['id' => 85012345678, 'topic' => 'Intro call', 'start_time' => '2026-11-05T19:30:00Z', 'duration' => 45, 'join_url' => 'https://zoom.us/j/85012345678', 'start_url' => 'https://zoom.us/s/85012345678?zak=x', 'password' => 'abc123'])]);

    $output = meetingOutput('zoom_create_meeting', ['topic' => 'Intro call', 'start_at' => '2026-11-05 14:30', 'timezone' => 'America/New_York', 'duration' => 45, 'agenda' => 'Say hello', 'auto_recording' => 'cloud']);

    expect($output)->toMatchArray(['meeting_id' => '85012345678', 'topic' => 'Intro call', 'join_url' => 'https://zoom.us/j/85012345678', 'password' => 'abc123']);

    $request = meetingRequest('/users/me/meetings');

    expect($request->header('Authorization'))->toBe(['Bearer test-token'])
        ->and($request['type'])->toBe(2)
        ->and($request['start_time'])->toBe('2026-11-05T19:30:00Z')
        ->and($request['timezone'])->toBe('UTC')
        ->and($request['duration'])->toBe(45)
        ->and($request['settings'])->toMatchArray(['waiting_room' => true, 'join_before_host' => false, 'mute_upon_entry' => true, 'auto_recording' => 'cloud']);
});

it('makes an instant meeting when no time is given, and keeps a time that carries its own offset', function () {
    Http::fake(['api.zoom.us/*' => Http::response(['id' => 85012345678])]);

    meetingOutput('zoom_create_meeting', ['topic' => 'Quick sync']);
    meetingOutput('zoom_create_meeting', ['topic' => 'Offset', 'start_at' => '2026-11-05T14:30:00+01:00', 'timezone' => 'America/New_York']);

    $requests = collect(Http::recorded())->map(fn (array $pair): Request => $pair[0])->values();

    expect($requests[0]['type'])->toBe(1)->and($requests[0]->data())->not->toHaveKey('start_time')
        ->and($requests[1]['start_time'])->toBe('2026-11-05T13:30:00Z');
});

it('refuses a Zoom meeting that cannot be made, before calling Zoom', function () {
    Http::fake();

    expect(fn () => executeNode('zoom_create_meeting', ['access_token' => 't', 'topic' => ' ']))->toThrow(InvalidArgumentException::class, 'topic')
        ->and(fn () => executeNode('zoom_create_meeting', ['access_token' => 't', 'topic' => 'x', 'password' => 'way too long!!']))->toThrow(InvalidArgumentException::class, 'passcode')
        ->and(fn () => executeNode('zoom_create_meeting', ['access_token' => 't', 'topic' => 'x', 'start_at' => 'whenever']))->toThrow(InvalidArgumentException::class, 'not a date')
        ->and(fn () => executeNode('zoom_create_meeting', ['access_token' => 't', 'topic' => 'x', 'start_at' => '2026-11-05 10:00', 'timezone' => 'Mars/Olympus']))->toThrow(InvalidArgumentException::class, 'not a timezone')
        ->and(fn () => executeNode('zoom_create_meeting', ['access_token' => 't', 'topic' => 'x', 'duration' => 2000]))->toThrow(InvalidArgumentException::class, '24 hours');

    Http::assertNothingSent();
});

it('reads, changes, lists and deletes Zoom meetings', function () {
    $reads = 0;

    Http::fake([
        // One router, because a URL that matches two stubs would use up an answer meant for the other.
        'api.zoom.us/v2/meetings/*' => function (Request $request) use (&$reads) {
            if (str_contains($request->url(), '/registrants')) {
                return Http::response(['registrants' => [['id' => 'r1', 'email' => 'ada@example.com', 'first_name' => 'Ada', 'status' => 'approved', 'join_url' => 'https://zoom.us/w/r1']]]);
            }

            if ($request->method() === 'GET') {
                return Http::response(++$reads === 1 ? ['id' => 85012345678, 'topic' => 'Old', 'duration' => 30] : ['id' => 85012345678, 'topic' => 'New', 'duration' => 60]);
            }

            return Http::response(null, 204);
        },
        'api.zoom.us/v2/users/me/meetings*' => Http::response(['meetings' => [['id' => 1, 'topic' => 'A'], ['id' => 2, 'topic' => 'B']]]),
    ]);

    expect(meetingOutput('zoom_get_meeting', ['meeting_id' => '850 1234 5678']))->toMatchArray(['topic' => 'Old'])
        ->and(meetingOutput('zoom_update_meeting', ['meeting_id' => '85012345678', 'topic' => 'New', 'duration' => 60]))->toMatchArray(['topic' => 'New', 'duration' => 60])
        ->and(meetingOutput('zoom_list_meetings', ['which' => 'live'])['count'])->toBe(2)
        ->and(meetingOutput('zoom_list_registrants', ['meeting_id' => '85012345678'])['registrants'][0])->toMatchArray(['email' => 'ada@example.com', 'status' => 'approved'])
        ->and(meetingOutput('zoom_delete_meeting', ['meeting_id' => '85012345678', 'notify' => false]))->toBe(['meeting_id' => '85012345678', 'deleted' => true]);

    expect(meetingRequest('/meetings/85012345678', 'PATCH')->data())->toBe(['topic' => 'New', 'duration' => 60])
        ->and(meetingRequest('/meetings/85012345678', 'DELETE')['cancel_meeting_reminder'])->toBe('false')
        ->and(meetingRequest('/users/me/meetings')['type'])->toBe('live');
});

it('refuses a Zoom meeting ID that is not one before it can reach an address', function () {
    Http::fake();

    foreach (['../../users/me', '123', ' ', '85012345678/recordings', 'abc'] as $bad) {
        expect(fn () => executeNode('zoom_get_meeting', ['access_token' => 't', 'meeting_id' => $bad]))->toThrow(InvalidArgumentException::class, 'not a Zoom meeting ID');
    }

    expect(fn () => executeNode('zoom_update_meeting', ['access_token' => 't', 'meeting_id' => '85012345678']))->toThrow(InvalidArgumentException::class, 'at least one');

    Http::assertNothingSent();
});

it('registers a person for a meeting and returns their own link', function () {
    Http::fake(['api.zoom.us/v2/meetings/85012345678/registrants' => Http::response(['registrant_id' => 'r1', 'join_url' => 'https://zoom.us/w/r1'])]);

    $output = meetingOutput('zoom_add_registrant', ['meeting_id' => '85012345678', 'email' => 'ADA@Example.com', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'company' => 'Analytical Engines']);

    expect($output)->toMatchArray(['registrant_id' => 'r1', 'join_url' => 'https://zoom.us/w/r1', 'email' => 'ada@example.com'])
        ->and(meetingRequest('/registrants')->data())->toMatchArray(['email' => 'ada@example.com', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'org' => 'Analytical Engines', 'auto_approve' => true])
        ->and(fn () => executeNode('zoom_add_registrant', ['access_token' => 't', 'meeting_id' => '85012345678', 'email' => 'bad', 'first_name' => 'A']))->toThrow(InvalidArgumentException::class, 'not an email')
        ->and(fn () => executeNode('zoom_add_registrant', ['access_token' => 't', 'meeting_id' => '85012345678', 'email' => 'a@b.com', 'first_name' => ' ']))->toThrow(InvalidArgumentException::class, 'first name');
});

it('lists Zoom recordings and says which have a transcript', function () {
    Http::fake(['api.zoom.us/v2/users/me/recordings*' => Http::response(['meetings' => [
        ['id' => 111, 'uuid' => 'u1==', 'topic' => 'Planning', 'start_time' => '2026-11-01T10:00:00Z', 'duration' => 30, 'share_url' => 'https://zoom.us/rec/share/x', 'recording_files' => [['file_type' => 'MP4', 'recording_type' => 'shared_screen_with_speaker_view', 'file_size' => 100, 'status' => 'completed'], ['file_type' => 'TRANSCRIPT', 'file_size' => 5, 'status' => 'completed']]],
        ['id' => 222, 'uuid' => 'u2==', 'topic' => 'Standup', 'recording_files' => [['file_type' => 'MP4']]],
    ]])]);

    $output = meetingOutput('zoom_list_recordings', ['from' => '2026-10-20', 'limit' => 5]);

    expect($output['count'])->toBe(2)
        ->and($output['recordings'][0])->toMatchArray(['meeting_id' => '111', 'topic' => 'Planning', 'has_transcript' => true])
        ->and($output['recordings'][1]['has_transcript'])->toBeFalse()
        ->and(meetingRequest('/users/me/recordings')['from'])->toBe('2026-10-20')
        ->and(meetingRequest('/users/me/recordings')['page_size'])->toEqual(5);
});

it('fetches a meeting transcript as readable text with speaker names', function () {
    $vtt = "WEBVTT\n\n1\n00:00:01.000 --> 00:00:04.000\nAda Lovelace: Welcome everyone.\n\n2\n00:00:05.000 --> 00:00:08.500\nBob: Thanks for having us.\nLet's begin.\n";

    Http::fake([
        'api.zoom.us/v2/meetings/85012345678/recordings' => Http::response(['topic' => 'Planning', 'start_time' => '2026-11-01T10:00:00Z', 'recording_files' => [['file_type' => 'MP4', 'download_url' => 'https://zoom.us/rec/download/video'], ['file_type' => 'TRANSCRIPT', 'download_url' => 'https://zoom.us/rec/download/transcript']]]),
        'zoom.us/rec/download/transcript' => Http::response($vtt, 200, ['Content-Type' => 'text/vtt']),
    ]);

    $plain = meetingOutput('zoom_get_transcript', ['meeting_id' => '85012345678']);
    $timed = meetingOutput('zoom_get_transcript', ['meeting_id' => '85012345678', 'timestamps' => true]);

    expect($plain['transcript'])->toBe("Ada Lovelace: Welcome everyone.\nBob: Thanks for having us.\nLet's begin.")
        ->and($plain)->toMatchArray(['topic' => 'Planning', 'meeting_id' => '85012345678'])
        ->and($timed['transcript'])->toStartWith('[00:00:01] Ada Lovelace: Welcome everyone.')
        ->and(meetingRequest('/rec/download/transcript')->header('Authorization'))->toBe(['Bearer test-token']);
});

it('says plainly when there is no transcript yet', function () {
    Http::fake(['api.zoom.us/v2/meetings/*/recordings' => Http::response(['recording_files' => [['file_type' => 'MP4', 'download_url' => 'https://zoom.us/rec/download/v']]])]);

    expect(fn () => executeNode('zoom_get_transcript', ['access_token' => 't', 'meeting_id' => '85012345678']))->toThrow(RuntimeException::class, 'no transcript yet');
});

it('starts a flow for each new Zoom recording', function () {
    $node = app(NodeRegistry::class)->resolve('zoom_list_recordings');

    expect($node)->toBeInstanceOf(SupportsTriggerMode::class)
        ->and($node->triggerName())->toBe('Zoom: New recording')
        ->and($node->triggerItemId(['uuid' => 'u1==', 'meeting_id' => '1']))->toBe('u1==')
        ->and($node->triggerItemId(['meeting_id' => '1']))->toBe('1')
        ->and($node->triggerReadConfig([])['limit'])->toBe(100);
});

/* ----------------------------------------------------------------- Calendly */

/**
 * @param  array<string, mixed>  $routes
 */
function fakeCalendly(array $routes): void
{
    Http::fake([
        'api.calendly.com/users/me' => Http::response(['resource' => ['uri' => 'https://api.calendly.com/users/ME', 'name' => 'Pat Host', 'email' => 'pat@example.com', 'timezone' => 'Europe/London', 'scheduling_url' => 'https://calendly.com/pat', 'current_organization' => 'https://api.calendly.com/organizations/ORG']]),
        ...$routes,
    ]);
}

it('lists booked meetings for the signed-in person, soonest first', function () {
    fakeCalendly(['api.calendly.com/scheduled_events*' => Http::response(['collection' => [[
        'uri' => 'https://api.calendly.com/scheduled_events/EVT-12345678', 'name' => '30 Minute Meeting', 'status' => 'active', 'start_time' => '2026-11-05T10:00:00Z', 'end_time' => '2026-11-05T10:30:00Z',
        'event_type' => 'https://api.calendly.com/event_types/ET-12345678', 'location' => ['type' => 'zoom', 'join_url' => 'https://zoom.us/j/1'],
        'invitees_counter' => ['active' => 1, 'total' => 1], 'event_memberships' => [['user_email' => 'pat@example.com']], 'created_at' => '2026-11-01T09:00:00Z',
    ]]])]);

    $output = meetingOutput('calendly_list_scheduled_events', ['from' => '2026-11-05T00:00:00Z', 'limit' => 10]);

    expect($output['count'])->toBe(1)
        ->and($output['events'][0])->toMatchArray(['event_id' => 'EVT-12345678', 'name' => '30 Minute Meeting', 'status' => 'active', 'event_type' => 'ET-12345678', 'join_url' => 'https://zoom.us/j/1', 'invitees' => 1, 'hosts' => ['pat@example.com']]);

    $request = meetingRequest('/scheduled_events');

    expect($request['user'])->toBe('https://api.calendly.com/users/ME')
        ->and($request['status'])->toBe('active')
        ->and($request['min_start_time'])->toBe('2026-11-05T00:00:00Z')
        ->and($request['sort'])->toBe('start_time:asc');
});

it('looks the signed-in person up once, not for every call', function () {
    fakeCalendly(['api.calendly.com/event_types*' => Http::response(['collection' => [['uri' => 'https://api.calendly.com/event_types/ET-12345678', 'name' => 'Intro', 'duration' => 15, 'active' => true, 'scheduling_url' => 'https://calendly.com/pat/intro']]])]);

    meetingOutput('calendly_list_event_types', []);
    meetingOutput('calendly_list_event_types', []);

    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/users/me'))->count())->toBe(1);
});

it('lists meeting types and the signed-in profile', function () {
    fakeCalendly(['api.calendly.com/event_types*' => Http::response(['collection' => [['uri' => 'https://api.calendly.com/event_types/ET-12345678', 'name' => 'Intro', 'duration' => 15, 'active' => true, 'scheduling_url' => 'https://calendly.com/pat/intro', 'description_plain' => 'A quick hello']]])]);

    expect(meetingOutput('calendly_list_event_types', [])['event_types'][0])->toMatchArray(['event_type_id' => 'ET-12345678', 'name' => 'Intro', 'duration' => 15, 'booking_url' => 'https://calendly.com/pat/intro'])
        ->and(meetingOutput('calendly_get_current_user', []))->toMatchArray(['name' => 'Pat Host', 'email' => 'pat@example.com', 'timezone' => 'Europe/London', 'booking_page' => 'https://calendly.com/pat'])
        ->and(meetingRequest('/event_types')['active'])->toBe('true');
});

it('gets who booked, with their answers to your questions', function () {
    fakeCalendly(['api.calendly.com/scheduled_events/EVT-12345678/invitees*' => Http::response(['collection' => [[
        'name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'status' => 'active', 'timezone' => 'America/New_York', 'created_at' => '2026-11-01T09:00:00Z',
        'questions_and_answers' => [['question' => 'What would you like to discuss?', 'answer' => 'Pricing'], ['question' => 'Company', 'answer' => 'Analytical Engines']],
        'cancel_url' => 'https://calendly.com/cancellations/x', 'reschedule_url' => 'https://calendly.com/reschedulings/x', 'tracking' => ['utm_source' => 'newsletter'],
    ]]])]);

    $output = meetingOutput('calendly_list_invitees', ['event' => 'https://api.calendly.com/scheduled_events/EVT-12345678']);

    expect($output['count'])->toBe(1)
        ->and($output['first'])->toMatchArray(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'source' => 'newsletter'])
        ->and($output['first']['answers'])->toBe(['What would you like to discuss?' => 'Pricing', 'Company' => 'Analytical Engines']);
});

it('accepts a Calendly event as an ID or a whole address, and refuses anything else', function () {
    Http::fake();

    foreach (['../../users/me', 'x', ' ', 'EVT 12345678', 'https://evil.test/a/../../etc'] as $bad) {
        expect(fn () => executeNode('calendly_get_event', ['access_token' => 't', 'event' => $bad]))->toThrow(InvalidArgumentException::class, 'not a booked meeting');
    }

    Http::assertNothingSent();
});

it('cancels a booked meeting and makes a one-time booking link', function () {
    fakeCalendly([
        'api.calendly.com/scheduled_events/EVT-12345678/cancellation' => Http::response(['resource' => ['canceled_by' => 'Pat Host']], 201),
        'api.calendly.com/scheduling_links' => Http::response(['resource' => ['booking_url' => 'https://calendly.com/d/abc-123/intro', 'owner' => 'x', 'owner_type' => 'EventType']], 201),
    ]);

    expect(meetingOutput('calendly_cancel_event', ['event' => 'EVT-12345678', 'reason' => 'Clash']))->toBe(['event_id' => 'EVT-12345678', 'cancelled' => true])
        ->and(meetingOutput('calendly_create_scheduling_link', ['event_type' => 'ET-12345678']))->toBe(['booking_url' => 'https://calendly.com/d/abc-123/intro', 'event_type_id' => 'ET-12345678']);

    expect(meetingRequest('/cancellation')->data())->toBe(['reason' => 'Clash'])
        ->and(meetingRequest('/scheduling_links')->data())->toBe(['max_event_count' => 1, 'owner' => 'https://api.calendly.com/event_types/ET-12345678', 'owner_type' => 'EventType']);
});

it('lists open times within a week and offers the next one', function () {
    fakeCalendly(['api.calendly.com/event_type_available_times*' => Http::response(['collection' => [
        ['status' => 'available', 'start_time' => '2026-11-05T10:00:00Z', 'scheduling_url' => 'https://calendly.com/pat/intro/2026-11-05T10:00', 'invitees_remaining' => 1],
        ['status' => 'unavailable', 'start_time' => '2026-11-05T10:30:00Z'],
        ['status' => 'available', 'start_time' => '2026-11-05T11:00:00Z', 'scheduling_url' => 'https://calendly.com/pat/intro/2026-11-05T11:00', 'invitees_remaining' => 1],
    ]])]);

    $output = meetingOutput('calendly_list_available_times', ['event_type' => 'ET-12345678', 'days' => 30, 'from' => '2099-01-01T09:00:00Z']);

    expect($output['count'])->toBe(2)
        ->and($output['next'])->toMatchArray(['start_time' => '2026-11-05T10:00:00Z'])
        ->and($output['times'][1]['booking_url'])->toContain('11:00');

    $request = meetingRequest('/event_type_available_times');

    expect($request['start_time'])->toBe('2099-01-01T09:00:00Z')
        ->and($request['end_time'])->toBe('2099-01-08T09:00:00Z')
        ->and($request['event_type'])->toBe('https://api.calendly.com/event_types/ET-12345678');
});

it('starts a flow for each new Calendly booking', function () {
    $node = app(NodeRegistry::class)->resolve('calendly_list_scheduled_events');

    expect($node)->toBeInstanceOf(SupportsTriggerMode::class)
        ->and($node->triggerName())->toBe('Calendly: New booking')
        ->and($node->triggerItemId(['event_id' => 'EVT-1']))->toBe('EVT-1')
        ->and($node->triggerReadConfig(['status' => 'canceled'])['status'])->toBe('active');
});

/* ------------------------------------------------------------------ Cal.com */

it('lists Cal.com meeting types and bookings, naming the API version each was written for', function () {
    Http::fake([
        'api.cal.com/v2/event-types*' => Http::response(['status' => 'success', 'data' => [['id' => 7, 'title' => 'Intro', 'slug' => 'intro', 'lengthInMinutes' => 15, 'hidden' => false]]]),
        'api.cal.com/v2/bookings*' => Http::response(['status' => 'success', 'data' => [['uid' => 'bk_123456', 'title' => 'Intro between Pat and Ada', 'status' => 'accepted', 'start' => '2026-11-05T10:00:00.000Z', 'end' => '2026-11-05T10:15:00.000Z', 'duration' => 15, 'meetingUrl' => 'https://cal.com/video/bk_123456', 'eventTypeId' => 7, 'attendees' => [['name' => 'Ada', 'email' => 'ada@example.com', 'timeZone' => 'Europe/London']], 'hosts' => [['name' => 'Pat', 'email' => 'pat@example.com']]]]]),
    ]);

    $types = meetingOutput('cal_com_list_event_types', []);
    $bookings = meetingOutput('cal_com_list_bookings', ['status' => 'past', 'attendee_email' => 'ADA@example.com', 'limit' => 5]);

    expect($types['event_types'][0])->toMatchArray(['event_type_id' => 7, 'title' => 'Intro', 'duration' => 15])
        ->and($bookings['bookings'][0])->toMatchArray(['booking_id' => 'bk_123456', 'status' => 'accepted', 'meeting_url' => 'https://cal.com/video/bk_123456', 'hosts' => ['pat@example.com']])
        ->and($bookings['bookings'][0]['attendees'][0])->toBe(['name' => 'Ada', 'email' => 'ada@example.com', 'timezone' => 'Europe/London'])
        ->and(meetingRequest('/event-types')->header('cal-api-version'))->toBe(['2024-06-14'])
        ->and(meetingRequest('/bookings')->header('cal-api-version'))->toBe(['2024-08-13'])
        ->and(meetingRequest('/bookings')['status'])->toBe('past')
        ->and(meetingRequest('/bookings')['attendeeEmail'])->toBe('ada@example.com');
});

it('books a Cal.com meeting at a time that is open, with the right body', function () {
    Http::fake(['api.cal.com/v2/bookings' => Http::response(['status' => 'success', 'data' => ['uid' => 'bk_123456', 'title' => 'Intro', 'status' => 'accepted', 'start' => '2026-11-05T15:00:00.000Z']])]);

    $output = meetingOutput('cal_com_create_booking', ['event_type_id' => 7, 'start_at' => '2026-11-05 10:00', 'timezone' => 'America/New_York', 'name' => 'Ada Lovelace', 'email' => 'ADA@example.com', 'notes' => 'Pricing', 'guests' => ['bob@example.com', 'nope']]);

    expect($output)->toMatchArray(['booking_id' => 'bk_123456', 'status' => 'accepted']);

    $request = meetingRequest('/bookings', 'POST');

    expect($request['start'])->toBe('2026-11-05T15:00:00.000Z')
        ->and($request['eventTypeId'])->toBe(7)
        ->and($request['attendee'])->toBe(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'timeZone' => 'America/New_York'])
        ->and($request['guests'])->toBe(['bob@example.com'])
        ->and($request['bookingFieldsResponses'])->toBe(['notes' => 'Pricing'])
        ->and($request->header('Authorization'))->toBe(['Bearer test-token']);
});

it('refuses a Cal.com booking that cannot be made, before calling Cal.com', function () {
    Http::fake();
    $good = ['access_token' => 't', 'event_type_id' => 7, 'start_at' => '2026-11-05 10:00', 'name' => 'Ada', 'email' => 'a@b.com'];

    expect(fn () => executeNode('cal_com_create_booking', [...$good, 'email' => 'bad']))->toThrow(InvalidArgumentException::class, 'not an email')
        ->and(fn () => executeNode('cal_com_create_booking', [...$good, 'name' => ' ']))->toThrow(InvalidArgumentException::class, 'name')
        ->and(fn () => executeNode('cal_com_create_booking', [...$good, 'event_type_id' => 0]))->toThrow(InvalidArgumentException::class, 'meeting type')
        ->and(fn () => executeNode('cal_com_create_booking', [...$good, 'start_at' => '']))->toThrow(InvalidArgumentException::class, 'empty')
        ->and(fn () => executeNode('cal_com_cancel_booking', ['access_token' => 't', 'booking_id' => '../x']))->toThrow(InvalidArgumentException::class, 'not a booking ID');

    Http::assertNothingSent();
});

it('flattens Cal.com open times by day and cancels a booking', function () {
    Http::fake([
        'api.cal.com/v2/slots*' => Http::response(['status' => 'success', 'data' => ['2026-11-05' => [['start' => '2026-11-05T09:00:00.000Z'], ['start' => '2026-11-05T09:30:00.000Z']], '2026-11-06' => [['start' => '2026-11-06T09:00:00.000Z']]]]),
        'api.cal.com/v2/bookings/bk_123456/cancel' => Http::response(['status' => 'success', 'data' => ['uid' => 'bk_123456', 'status' => 'cancelled']]),
    ]);

    $slots = meetingOutput('cal_com_list_slots', ['event_type_id' => 7, 'days' => 3, 'timezone' => 'Europe/London']);

    expect($slots['count'])->toBe(3)
        ->and($slots['next'])->toBe(['start_time' => '2026-11-05T09:00:00.000Z', 'day' => '2026-11-05'])
        ->and(meetingRequest('/slots')->header('cal-api-version'))->toBe(['2024-09-04'])
        ->and(meetingRequest('/slots')['timeZone'])->toBe('Europe/London')
        ->and(meetingOutput('cal_com_cancel_booking', ['booking_id' => 'bk_123456', 'reason' => 'Clash']))->toBe(['booking_id' => 'bk_123456', 'cancelled' => true])
        ->and(meetingRequest('/cancel')['cancellationReason'])->toBe('Clash');
});

it('starts a flow for each new Cal.com booking', function () {
    $node = app(NodeRegistry::class)->resolve('cal_com_list_bookings');

    expect($node)->toBeInstanceOf(SupportsTriggerMode::class)
        ->and($node->triggerName())->toBe('Cal.com: New booking')
        ->and($node->triggerItemId(['booking_id' => 'bk_1']))->toBe('bk_1')
        ->and($node->triggerReadConfig(['status' => 'past'])['status'])->toBe('upcoming');
});

/* --------------------------------------------------------------------- Teams */

const TEAMS_EVENT_ID = 'AAMkAGI2THVSAAA=AAMkAGI2THVSAAA';

it('schedules a Teams meeting as a calendar event with a Teams link, in UTC', function () {
    Http::fake(['graph.microsoft.com/v1.0/me/events' => Http::response(['id' => TEAMS_EVENT_ID, 'subject' => 'Intro call', 'start' => ['dateTime' => '2026-11-05T19:30:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-11-05T20:00:00.0000000', 'timeZone' => 'UTC'], 'onlineMeeting' => ['joinUrl' => 'https://teams.microsoft.com/l/meetup-join/abc'], 'organizer' => ['emailAddress' => ['address' => 'pat@example.com']], 'attendees' => [['emailAddress' => ['name' => 'Ada', 'address' => 'ada@example.com'], 'status' => ['response' => 'none']]], 'webLink' => 'https://outlook.office.com/x'])]);

    $output = meetingOutput('microsoft_teams_create_meeting', ['subject' => 'Intro call', 'start_at' => '2026-11-05 14:30', 'timezone' => 'America/New_York', 'duration' => 30, 'attendees' => ['ADA@example.com', 'ada@example.com', ' '], 'description' => 'Hello']);

    expect($output)->toMatchArray(['meeting_id' => TEAMS_EVENT_ID, 'subject' => 'Intro call', 'start_time' => '2026-11-05T19:30:00Z', 'end_time' => '2026-11-05T20:00:00Z', 'join_url' => 'https://teams.microsoft.com/l/meetup-join/abc', 'organizer' => 'pat@example.com'])
        ->and($output['attendees'][0])->toBe(['name' => 'Ada', 'email' => 'ada@example.com', 'response' => 'none']);

    $request = meetingRequest('/me/events', 'POST');

    expect($request['isOnlineMeeting'])->toBeTrue()
        ->and($request['onlineMeetingProvider'])->toBe('teamsForBusiness')
        ->and($request['start'])->toBe(['dateTime' => '2026-11-05T19:30:00', 'timeZone' => 'UTC'])
        ->and($request['end'])->toBe(['dateTime' => '2026-11-05T20:00:00', 'timeZone' => 'UTC'])
        ->and($request['attendees'])->toBe([['emailAddress' => ['address' => 'ada@example.com'], 'type' => 'required']])
        ->and($request->header('Prefer'))->toBe(['outlook.timezone="UTC"']);
});

it('refuses a Teams meeting that cannot be made, before calling Microsoft', function () {
    Http::fake();

    expect(fn () => executeNode('microsoft_teams_create_meeting', ['access_token' => 't', 'subject' => ' ', 'start_at' => '2026-11-05 10:00']))->toThrow(InvalidArgumentException::class, 'subject')
        ->and(fn () => executeNode('microsoft_teams_create_meeting', ['access_token' => 't', 'subject' => 'x', 'start_at' => '2026-11-05 10:00', 'attendees' => ['nope']]))->toThrow(InvalidArgumentException::class, 'not an email')
        ->and(fn () => executeNode('microsoft_teams_create_meeting', ['access_token' => 't', 'subject' => 'x', 'start_at' => '2026-11-05 10:00', 'attendees' => array_map(fn (int $i): string => "p{$i}@example.com", range(1, 101))]))->toThrow(InvalidArgumentException::class, 'at most 100')
        ->and(fn () => executeNode('microsoft_teams_get_meeting', ['access_token' => 't', 'meeting_id' => '../../me']))->toThrow(InvalidArgumentException::class, 'not a meeting ID');

    Http::assertNothingSent();
});

it('lists only the Teams meetings on the calendar, leaving out other events and cancelled ones', function () {
    Http::fake(['graph.microsoft.com/v1.0/me/calendarView*' => Http::response(['value' => [
        ['id' => 'ev1', 'subject' => 'Teams sync', 'isOnlineMeeting' => true, 'onlineMeetingProvider' => 'teamsForBusiness', 'onlineMeeting' => ['joinUrl' => 'https://teams.microsoft.com/l/1'], 'start' => ['dateTime' => '2026-11-05T10:00:00.0000000'], 'end' => ['dateTime' => '2026-11-05T10:30:00.0000000']],
        ['id' => 'ev2', 'subject' => 'Lunch', 'isOnlineMeeting' => false],
        ['id' => 'ev3', 'subject' => 'Zoom call', 'isOnlineMeeting' => true, 'onlineMeetingProvider' => 'unknown'],
        ['id' => 'ev4', 'subject' => 'Called off', 'isOnlineMeeting' => true, 'onlineMeetingProvider' => 'teamsForBusiness', 'isCancelled' => true],
    ]])]);

    $output = meetingOutput('microsoft_teams_list_meetings', ['days' => 14, 'limit' => 10]);

    expect($output['count'])->toBe(1)
        ->and($output['meetings'][0])->toMatchArray(['meeting_id' => 'ev1', 'subject' => 'Teams sync', 'start_time' => '2026-11-05T10:00:00Z']);

    expect(meetingRequest('/me/calendarView')['$orderby'])->toBe('start/dateTime');
});

it('cancels a Teams meeting with a note, and reads one back', function () {
    Http::fake([
        'graph.microsoft.com/v1.0/me/events/*/cancel' => Http::response(null, 202),
        'graph.microsoft.com/v1.0/me/events/*' => Http::response(['id' => TEAMS_EVENT_ID, 'subject' => 'Intro', 'isCancelled' => false]),
    ]);

    expect(meetingOutput('microsoft_teams_cancel_meeting', ['meeting_id' => TEAMS_EVENT_ID, 'comment' => 'Sorry, clash']))->toBe(['meeting_id' => TEAMS_EVENT_ID, 'cancelled' => true])
        ->and(meetingOutput('microsoft_teams_get_meeting', ['meeting_id' => TEAMS_EVENT_ID]))->toMatchArray(['meeting_id' => TEAMS_EVENT_ID, 'is_cancelled' => false])
        ->and(meetingRequest('/cancel')['comment'])->toBe('Sorry, clash')
        ->and(meetingRequest('/me/events/'.rawurlencode(TEAMS_EVENT_ID).'/cancel')->url())->toContain('%3D');
});

it('starts a flow for each new Teams meeting', function () {
    $node = app(NodeRegistry::class)->resolve('microsoft_teams_list_meetings');

    expect($node)->toBeInstanceOf(SupportsTriggerMode::class)
        ->and($node->triggerName())->toBe('Microsoft Teams: New meeting')
        ->and($node->triggerItemId(['meeting_id' => 'ev1']))->toBe('ev1')
        ->and($node->triggerReadConfig([])['days'])->toBe(30);
});

/* ------------------------------------------------------------------- shared */

it('explains a meeting-service failure in plain words, naming the service', function (string $type, array $config, int $status, string $expected) {
    Http::fake(['*' => Http::response(['message' => 'Details here'], $status)]);

    expect(fn () => executeNode($type, ['access_token' => 'k', ...$config]))->toThrow(RuntimeException::class, $expected);
})->with([
    'Zoom refuses' => ['zoom_list_meetings', [], 401, 'Zoom refused the API key'],
    'Zoom limited' => ['zoom_list_meetings', [], 429, 'Zoom is limiting requests'],
    'Calendly refuses' => ['calendly_get_current_user', [], 401, 'Calendly refused the API key'],
    'Cal.com rejects' => ['cal_com_list_event_types', [], 400, 'Cal.com rejected the request: Details here'],
    'Teams not found' => ['microsoft_teams_get_meeting', ['meeting_id' => TEAMS_EVENT_ID], 404, 'Microsoft Teams found nothing'],
]);

it('registers the meeting nodes under their own app categories', function () {
    $expected = [
        'zoom' => ['zoom_create_meeting', 'zoom_get_meeting', 'zoom_update_meeting', 'zoom_delete_meeting', 'zoom_list_meetings', 'zoom_add_registrant', 'zoom_list_registrants', 'zoom_list_recordings', 'zoom_get_transcript'],
        'calendly' => ['calendly_get_current_user', 'calendly_list_event_types', 'calendly_list_scheduled_events', 'calendly_get_event', 'calendly_list_invitees', 'calendly_cancel_event', 'calendly_create_scheduling_link', 'calendly_list_available_times'],
        'cal_com' => ['cal_com_list_event_types', 'cal_com_list_bookings', 'cal_com_create_booking', 'cal_com_cancel_booking', 'cal_com_list_slots'],
        'microsoft_teams' => ['microsoft_teams_create_meeting', 'microsoft_teams_get_meeting', 'microsoft_teams_list_meetings', 'microsoft_teams_cancel_meeting'],
    ];

    foreach ($expected as $category => $types) {
        foreach ($types as $type) {
            $node = app(NodeRegistry::class)->resolve($type);

            expect($node->category())->toBe($category)
                ->and($node->type())->toBe($type)
                ->and($node->configSchema()['properties'])->toHaveKey('credential_id')
                ->and($node->outputSchema()['properties'])->not->toBeEmpty();
        }
    }
});

it('marks inviting people as reaching them and cancelling or deleting as destructive', function () {
    $registry = app(NodeRegistry::class);

    foreach (['zoom_add_registrant', 'cal_com_create_booking', 'microsoft_teams_create_meeting'] as $type) {
        expect($registry->resolve($type)->effect([])->value)->toBe('external');
    }

    foreach (['zoom_delete_meeting', 'calendly_cancel_event', 'cal_com_cancel_booking', 'microsoft_teams_cancel_meeting'] as $type) {
        expect($registry->resolve($type)->effect([])->value)->toBe('destructive');
    }

    foreach (['zoom_create_meeting', 'zoom_update_meeting', 'calendly_create_scheduling_link'] as $type) {
        expect($registry->resolve($type)->effect([])->value)->toBe('write');
    }

    foreach (['zoom_get_meeting', 'zoom_get_transcript', 'calendly_list_invitees', 'cal_com_list_slots', 'microsoft_teams_list_meetings'] as $type) {
        expect($registry->resolve($type)->effect([])->isRead())->toBeTrue();
    }
});
