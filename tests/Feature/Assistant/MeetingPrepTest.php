<?php

use App\Ai\Assistant\MeetingBriefAgent;
use App\Enums\Assistant\AssistantBriefingRunStatus;
use App\Enums\Assistant\AssistantMeetingPrepStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Role;
use App\Jobs\Assistant\RunBriefingJob;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantMeeting;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Notifications\Assistant\MeetingBriefNotification;
use App\Services\Assistant\Meetings\ExternalMeeting;
use App\Services\Assistant\Meetings\MeetingPrepScheduler;
use App\Services\Assistant\Meetings\MeetingSync;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Priya', 'email' => 'priya@acme.test']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->scheduler = app(MeetingPrepScheduler::class);
    $this->config = $this->scheduler->config($this->assistant);
    $this->config->update(['enabled' => true]);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/assistant";

    $this->connect = function (string $key): ConnectorCredential {
        $connector = Connector::query()->where('key', $key)->first() ?? Connector::factory()->create(['key' => $key, 'name' => ucfirst($key)]);

        return ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($connector)->create([
            'scope' => ConnectorCredentialScope::Personal,
            'created_by' => $this->owner->id,
            'data' => ['access_token' => "token-{$key}"],
        ]);
    };

    $this->meeting = fn (array $attributes = []): AssistantMeeting => $this->assistant->meetings()->create([
        'provider_event_id' => 'evt_'.fake()->uuid(),
        'title' => 'Quarterly review with Globex',
        'starts_at' => now()->addMinutes(20),
        'attendees' => [['email' => 'sam@globex.test', 'name' => 'Sam']],
        'is_external' => true,
        ...$attributes,
    ]);

    $this->event = fn (string $id, array $attendees, string $start, array $extra = []): array => [
        'id' => $id,
        'summary' => "Meeting {$id}",
        'start' => ['dateTime' => $start],
        'end' => ['dateTime' => now()->parse($start)->addHour()->toRfc3339String()],
        'attendees' => $attendees,
        'htmlLink' => "https://calendar.test/{$id}",
        ...$extra,
    ];
});

it('treats a meeting as external when any guest is outside the owner domain', function (array $emails, bool $external) {
    $attendees = array_map(fn (string $email): array => ['email' => $email], $emails);

    expect(ExternalMeeting::isExternal('priya@acme.test', $attendees))->toBe($external);
})->with([
    'outside guest' => [['sam@globex.test'], true],
    'mixed' => [['lee@acme.test', 'sam@globex.test'], true],
    'all internal' => [['lee@acme.test'], false],
    'case differs' => [['Lee@ACME.test'], false],
]);

it('syncs meetings with guests, skipping solo, cancelled and far-off events', function () {
    ($this->connect)('google_calendar');
    Http::fake(['www.googleapis.com/*' => Http::response(['items' => [
        ($this->event)('ext', [['email' => 'priya@acme.test', 'self' => true], ['email' => 'sam@globex.test', 'displayName' => 'Sam']], now()->addHours(3)->toRfc3339String()),
        ($this->event)('int', [['email' => 'lee@acme.test']], now()->addHours(4)->toRfc3339String()),
        ($this->event)('solo', [], now()->addHours(5)->toRfc3339String()),
        ($this->event)('cancelled', [['email' => 'sam@globex.test']], now()->addHours(6)->toRfc3339String(), ['status' => 'cancelled']),
        ($this->event)('far', [['email' => 'sam@globex.test']], now()->addDays(10)->toRfc3339String()),
        ($this->event)('room', [['email' => 'room@resource.calendar.google.com', 'resource' => true]], now()->addHours(7)->toRfc3339String()),
    ]])]);

    expect(app(MeetingSync::class)->sync($this->assistant))->toBe(2);

    $meetings = $this->assistant->meetings()->get()->keyBy('provider_event_id');
    expect($meetings->keys()->sort()->values()->all())->toBe(['ext', 'int'])
        ->and($meetings['ext']->is_external)->toBeTrue()
        ->and($meetings['ext']->attendees)->toBe([['email' => 'sam@globex.test', 'name' => 'Sam']])
        ->and($meetings['int']->is_external)->toBeFalse();
});

it('drops meetings that left the calendar unless they were already prepped', function () {
    ($this->connect)('google_calendar');
    $gone = ($this->meeting)(['starts_at' => now()->addHours(2)]);
    $prepped = ($this->meeting)(['starts_at' => now()->addHours(2), 'prep_status' => AssistantMeetingPrepStatus::Prepared]);
    Http::fake(['www.googleapis.com/*' => Http::response(['items' => []])]);

    app(MeetingSync::class)->sync($this->assistant);

    expect(AssistantMeeting::query()->find($gone->id))->toBeNull()
        ->and(AssistantMeeting::query()->find($prepped->id))->not->toBeNull();
});

it('starts briefs for external meetings about to begin, once', function () {
    Queue::fake();
    $soon = ($this->meeting)();
    ($this->meeting)(['is_external' => false]);
    ($this->meeting)(['starts_at' => now()->addHours(3)]);

    expect($this->scheduler->startDue())->toBe(1)
        ->and($this->scheduler->startDue())->toBe(0)
        ->and($soon->refresh()->prep_status)->toBe(AssistantMeetingPrepStatus::Preparing);

    Queue::assertPushed(RunBriefingJob::class, 1);
});

it('preps internal meetings too when the scope is all meetings', function () {
    Queue::fake();
    $this->config->update(['settings' => [...$this->config->settings, 'scope' => 'all']]);
    ($this->meeting)(['is_external' => false]);

    expect($this->scheduler->startDue())->toBe(1);
});

it('does not prep automatically when automatic prep is off', function () {
    Queue::fake();
    $this->config->update(['settings' => [...$this->config->settings, 'auto' => false]]);
    ($this->meeting)();

    expect($this->scheduler->startDue())->toBe(0);
});

it('writes a brief from the guests email history and delivers it once', function () {
    Notification::fake();
    ($this->connect)('gmail');
    $this->config->update(['delivery' => ['email' => true]]);
    Http::fake([
        'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response(['messages' => [['id' => 'm1']]]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/m1*' => Http::response([
            'snippet' => 'Pricing for the renewal is still open.',
            'payload' => ['headers' => [['name' => 'From', 'value' => 'Sam <sam@globex.test>'], ['name' => 'Subject', 'value' => 'Renewal']]],
        ]),
    ]);
    MeetingBriefAgent::fake([['summary' => 'Renewal review with Sam; pricing is still open.', 'document' => "## Open questions\n- Renewal pricing"]]);
    $meeting = ($this->meeting)();

    $run = $this->scheduler->prepareNow($meeting)->refresh();

    expect($run->status)->toBe(AssistantBriefingRunStatus::Completed)
        ->and($run->summary)->toContain('pricing')
        ->and($run->document)->toContain('## Open questions')
        ->and($meeting->refresh()->prep_status)->toBe(AssistantMeetingPrepStatus::Prepared);

    Http::assertSent(fn (HttpRequest $request) => str_contains(urldecode($request->url()), 'from:sam@globex.test OR to:sam@globex.test'));
    MeetingBriefAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Pricing for the renewal is still open.'));
    Notification::assertSentToTimes($this->owner, MeetingBriefNotification::class, 1);
});

it('still writes a brief when no app has anything on the meeting', function () {
    MeetingBriefAgent::fake([['summary' => 'First meeting with Sam.', 'document' => '']]);

    $run = $this->scheduler->prepareNow(($this->meeting)())->refresh();

    expect($run->status)->toBe(AssistantBriefingRunStatus::Completed);
    MeetingBriefAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'No related email or files were found.'));
});

it('marks the meeting failed when the brief cannot be written', function () {
    MeetingBriefAgent::fake(fn () => throw new RuntimeException('provider down'));
    $meeting = ($this->meeting)();

    $run = $this->scheduler->prepareNow($meeting)->refresh();

    expect($run->status)->toBe(AssistantBriefingRunStatus::Failed)
        ->and($meeting->refresh()->prep_status)->toBe(AssistantMeetingPrepStatus::Failed);
});

it('lists upcoming meetings with their briefs and prepares one on demand', function () {
    Queue::fake();
    $meeting = ($this->meeting)(['starts_at' => now()->addDays(2)]);
    Passport::actingAs($this->owner);

    $this->getJson("{$this->base}/meetings")
        ->assertSuccessful()
        ->assertJsonPath('data.calendar_connected', false)
        ->assertJsonPath('data.meetings.0.id', $meeting->id)
        ->assertJsonPath('data.meetings.0.brief', null);

    $this->postJson("{$this->base}/meetings/{$meeting->id}/prepare")
        ->assertAccepted()
        ->assertJsonPath('data.meeting.prep_status', 'preparing')
        ->assertJsonPath('data.meeting.brief.status', 'queued');
});

it('keeps meetings private to their owner', function () {
    $meeting = ($this->meeting)();
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    Passport::actingAs($member);

    $this->postJson("{$this->base}/meetings/{$meeting->id}/prepare")->assertNotFound();
    $this->getJson("{$this->base}/meetings")->assertSuccessful()->assertJsonCount(0, 'data.meetings');
});

it('saves meeting prep settings and refuses run now for it', function () {
    Passport::actingAs($this->owner);

    $this->putJson("{$this->base}/briefings/meeting_prep", ['settings' => ['auto' => false, 'minutes_before' => 15, 'scope' => 'all']])
        ->assertSuccessful()
        ->assertJsonPath('data.config.settings.minutes_before', 15);

    $this->putJson("{$this->base}/briefings/meeting_prep", ['settings' => ['minutes_before' => 1, 'scope' => 'everyone']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['settings.minutes_before', 'settings.scope']);

    $this->postJson("{$this->base}/briefings/meeting_prep/run-now")->assertStatus(422);
    $this->getJson("{$this->base}/briefings/weekly")->assertNotFound();
});
