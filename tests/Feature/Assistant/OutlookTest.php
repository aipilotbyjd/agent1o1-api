<?php

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Assistant\Assistant;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Services\Assistant\Briefings\BriefingSources;
use App\Services\Assistant\Inbox\MailMessage;
use App\Services\Assistant\Inbox\OutlookMailbox;
use App\Services\Assistant\Meetings\MeetingSync;
use App\Services\Assistant\Tools\ConnectorToolProvider;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Priya', 'email' => 'priya@acme.test']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);

    $connector = Connector::query()->where('key', 'outlook')->first() ?? Connector::factory()->create(['key' => 'outlook', 'name' => 'Outlook']);
    $this->credential = ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($connector)->create([
        'scope' => ConnectorCredentialScope::Personal,
        'created_by' => $this->owner->id,
        'data' => ['access_token' => 'graph-token'],
    ]);

    $this->graph = 'https://graph.microsoft.com/v1.0/me';
});

it('offers Outlook mail and calendar as tools once connected', function () {
    $session = $this->assistant->sessions()->create(['last_activity_at' => now()]);
    $names = collect(app(ConnectorToolProvider::class)->toolsFor($this->assistant, $session))->map->name();

    expect($names)->toContain('outlook_list_messages', 'outlook_send_email', 'outlook_create_event', 'outlook_list_events');
});

it('reads an Outlook message in the shape Smart Inbox expects', function () {
    Http::fake(["{$this->graph}/messages/m1*" => Http::response([
        'id' => 'm1', 'conversationId' => 'c1', 'subject' => 'Renewal',
        'from' => ['emailAddress' => ['name' => 'Sam', 'address' => 'sam@globex.test']],
        'toRecipients' => [['emailAddress' => ['address' => 'priya@acme.test']]],
        'ccRecipients' => [['emailAddress' => ['name' => 'Lee', 'address' => 'lee@acme.test']]],
        'receivedDateTime' => '2026-10-03T08:00:00Z', 'internetMessageId' => '<m1@globex.test>',
        'body' => ['contentType' => 'text', 'content' => 'Can you confirm?'], 'bodyPreview' => 'Can you confirm?',
        'categories' => ['Clients'],
        'internetMessageHeaders' => [['name' => 'List-Unsubscribe', 'value' => '<mailto:x>']],
    ])]);

    $message = (new OutlookMailbox($this->credential))->message('m1');

    expect($message)
        ->from->toBe('Sam <sam@globex.test>')
        ->cc->toBe(['Lee <lee@acme.test>'])
        ->threadId->toBe('c1')
        ->body->toBe('Can you confirm?')
        ->labelIds->toBe(['INBOX', 'Clients'])
        ->isBulk->toBeTrue();
});

it('files mail under categories and archives by moving it', function () {
    Http::fake([
        "{$this->graph}/messages/m1/move" => Http::response(['id' => 'm1']),
        "{$this->graph}/messages/m1*" => fn (HttpRequest $request) => $request->method() === 'GET'
            ? Http::response(['categories' => ['Clients']])
            : Http::response([]),
    ]);

    (new OutlookMailbox($this->credential))->modify('m1', ['Newsletters'], ['INBOX']);

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'PATCH' && $request['categories'] === ['Clients', 'Newsletters']);
    Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/messages/m1/move') && $request['destinationId'] === 'archive');
});

it('drafts a reply-all in the thread and spots a sent draft', function () {
    Http::fake([
        "{$this->graph}/messages/m1/createReplyAll" => Http::response(['id' => 'd1']),
        "{$this->graph}/messages/d1*" => fn (HttpRequest $request) => $request->method() === 'GET'
            ? Http::response(['isDraft' => false, 'body' => ['content' => 'Sent text']])
            : Http::response(['id' => 'd1']),
    ]);
    $mailbox = new OutlookMailbox($this->credential);
    $original = new MailMessage('m1', 'c1', 'sam@globex.test', null, [], [], 'Renewal', now(), null, '', '', [], false);

    expect($mailbox->saveDraft(null, $original, ['sam@globex.test'], ['Lee <lee@acme.test>'], 'Confirmed.'))->toBe('d1')
        ->and($mailbox->draftBody('d1'))->toBeNull();

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'PATCH'
        && $request['body']['content'] === 'Confirmed.'
        && $request['ccRecipients'] === [['emailAddress' => ['address' => 'lee@acme.test']]]);
});

it('creates a new category when a label is renamed', function () {
    Http::fake([
        "{$this->graph}/outlook/masterCategories" => fn (HttpRequest $request) => $request->method() === 'GET'
            ? Http::response(['value' => [['displayName' => 'Clients']]])
            : Http::response(['displayName' => 'Customers']),
    ]);

    expect((new OutlookMailbox($this->credential))->renameLabel('Clients', 'Customers'))->toBe('Customers');
});

it('reads new Outlook mail and the next day of meetings for the Daily report', function () {
    Http::fake([
        "{$this->graph}/mailFolders/inbox/messages*" => Http::response(['value' => [[
            'id' => 'm1', 'subject' => 'Invoice', 'bodyPreview' => 'Attached.', 'receivedDateTime' => now()->toIso8601String(),
            'webLink' => 'https://outlook.test/m1', 'from' => ['emailAddress' => ['address' => 'billing@globex.test']],
        ]]]),
        "{$this->graph}/calendarView*" => Http::response(['value' => [[
            'id' => 'e1', 'subject' => 'Standup', 'start' => ['dateTime' => now()->addHours(2)->utc()->format('Y-m-d\TH:i:s')],
            'attendees' => [['emailAddress' => ['address' => 'lee@acme.test']]],
        ]]]),
    ]);

    $items = app(BriefingSources::class)->for('outlook')->collect($this->assistant, $this->credential, now()->subDay(), 10);

    expect(collect($items)->pluck('title')->all())->toBe(['Invoice', 'Standup'])
        ->and($items[1]['people'])->toBe(['lee@acme.test']);
});

it('syncs meetings from an Outlook calendar', function () {
    Http::fake(["{$this->graph}/calendarView*" => Http::response(['value' => [
        ['id' => 'e1', 'subject' => 'Globex review', 'start' => ['dateTime' => now()->addDay()->utc()->format('Y-m-d\TH:i:s')],
            'end' => ['dateTime' => now()->addDay()->addHour()->utc()->format('Y-m-d\TH:i:s')],
            'attendees' => [['emailAddress' => ['address' => 'sam@globex.test', 'name' => 'Sam'], 'type' => 'required']]],
        ['id' => 'e2', 'subject' => 'Focus time', 'start' => ['dateTime' => now()->addDay()->utc()->format('Y-m-d\TH:i:s')], 'attendees' => []],
        ['id' => 'e3', 'subject' => 'Cancelled', 'isCancelled' => true, 'start' => ['dateTime' => now()->addDay()->utc()->format('Y-m-d\TH:i:s')],
            'attendees' => [['emailAddress' => ['address' => 'sam@globex.test']]]],
    ]])]);

    expect(app(MeetingSync::class)->sync($this->assistant))->toBe(1);

    expect($this->assistant->meetings()->sole())
        ->title->toBe('Globex review')
        ->is_external->toBeTrue()
        ->attendees->toBe([['email' => 'sam@globex.test', 'name' => 'Sam']]);
});

it('turns on Smart Inbox with Outlook and creates its categories', function () {
    PlanGrant::factory()->forWorkspace($this->workspace)
        ->forPlan(Plan::factory()->create(['features' => ['smart_inbox' => true]]))
        ->active()
        ->create();
    Http::fake([
        "{$this->graph}/outlook/masterCategories" => fn (HttpRequest $request) => $request->method() === 'GET'
            ? Http::response(['value' => []])
            : Http::response(['displayName' => $request['displayName']]),
    ]);
    Passport::actingAs($this->owner);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/assistant/inbox/enable", ['provider' => 'outlook'])
        ->assertOk()
        ->assertJsonPath('data.config.enabled', true)
        ->assertJsonPath('data.config.provider', 'outlook')
        ->assertJsonPath('data.available.outlook_connected', true);

    expect($this->assistant->inbox->labels()->whereNotNull('provider_label_id')->count())->toBeGreaterThan(0);
});
