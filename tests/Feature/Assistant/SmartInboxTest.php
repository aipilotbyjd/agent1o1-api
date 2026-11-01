<?php

use App\Ai\Assistant\AssistantAgent;
use App\Ai\Assistant\InboxClassifierAgent;
use App\Ai\Assistant\ReplyDrafterAgent;
use App\Enums\Assistant\AssistantInboxDraftStatus;
use App\Enums\Assistant\AssistantInboxMessageStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Role;
use App\Jobs\Assistant\CheckInboxJob;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantInboxConfig;
use App\Models\Assistant\AssistantInboxMessage;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Services\Assistant\Inbox\GmailMailbox;
use App\Services\Assistant\Inbox\InboxAccess;
use App\Services\Assistant\Inbox\InboxProcessor;
use App\Services\Assistant\Inbox\Mailbox;
use App\Services\Assistant\Inbox\MailboxFactory;
use App\Services\Assistant\Inbox\MailMessage;
use App\Services\Assistant\Inbox\OutlookMailbox;
use App\Services\Workspaces\WorkspaceService;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

/**
 * An in-memory mailbox: what Smart Inbox reads and everything it changes.
 */
class FakeMailbox implements Mailbox
{
    /** @var array<string, MailMessage> */
    public array $messages = [];

    /** @var array<string, string> id => name */
    public array $labelNames = ['Label_existing' => 'Clients'];

    /** @var list<array{0: string, 1: list<string>, 2: list<string>}> */
    public array $modified = [];

    /** @var array<string, array{to: list<string>, cc: list<string>, body: string}> */
    public array $drafts = [];

    /** @var list<string> */
    public array $sentTo = [];

    private int $nextId = 1;

    public function ownAddress(): string
    {
        return 'priya@acme.test';
    }

    public function newMessageIds(CarbonInterface $since, int $limit): array
    {
        return array_keys($this->messages);
    }

    public function message(string $id): MailMessage
    {
        return $this->messages[$id];
    }

    public function labels(): array
    {
        return collect($this->labelNames)->map(fn (string $name, string $id): array => ['id' => $id, 'name' => $name, 'user' => true])->values()->all();
    }

    public function createLabel(string $name): string
    {
        $id = 'Label_'.$this->nextId++;
        $this->labelNames[$id] = $name;

        return $id;
    }

    public function renameLabel(string $id, string $name): string
    {
        $this->labelNames[$id] = $name;

        return $id;
    }

    public function isOwnersLabel(string $id): bool
    {
        return str_starts_with($id, 'Label_');
    }

    public function modify(string $messageId, array $add, array $remove): void
    {
        $this->modified[] = [$messageId, $add, $remove];
    }

    /** @var array<string, bool> thread id => whether someone else replied */
    public array $threadReplies = [];

    public function threadHasReplyAfter(string $threadId, CarbonInterface $after): bool
    {
        return $this->threadReplies[$threadId] ?? false;
    }

    public function hasSentTo(string $address): bool
    {
        return in_array($address, $this->sentTo, true);
    }

    public function recentRepliesTo(string $address, int $limit): array
    {
        return ['Thanks Sam, sounds good. Priya'];
    }

    public function saveDraft(?string $draftId, MailMessage $original, array $to, array $cc, string $body): string
    {
        $draftId ??= 'draft_'.$this->nextId++;
        $this->drafts[$draftId] = ['to' => $to, 'cc' => $cc, 'body' => $body];

        return $draftId;
    }

    public function draftBody(string $draftId): ?string
    {
        return $this->drafts[$draftId]['body'] ?? null;
    }

    public function add(string $id, array $overrides = []): MailMessage
    {
        return $this->messages[$id] = new MailMessage(...[
            'id' => $id,
            'threadId' => "thread_{$id}",
            'from' => 'Sam <sam@globex.test>',
            'replyTo' => null,
            'to' => ['priya@acme.test'],
            'cc' => ['Lee <lee@acme.test>'],
            'subject' => 'Contract renewal',
            'receivedAt' => now(),
            'messageIdHeader' => "<{$id}@globex.test>",
            'body' => 'Can you confirm the renewal terms by Friday?',
            'snippet' => 'Can you confirm the renewal terms by Friday?',
            'labelIds' => ['INBOX', 'UNREAD'],
            'isBulk' => false,
            ...$overrides,
        ]);
    }
}

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Priya', 'email' => 'priya@acme.test']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/assistant";

    $this->givePro = fn () => PlanGrant::factory()->forWorkspace($this->workspace)
        ->forPlan(Plan::factory()->create(['features' => ['smart_inbox' => true]]))
        ->active()
        ->create();

    $this->connectGmail = function (): ConnectorCredential {
        $connector = Connector::query()->where('key', 'gmail')->first() ?? Connector::factory()->create(['key' => 'gmail', 'name' => 'Gmail']);

        return ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($connector)->create([
            'scope' => ConnectorCredentialScope::Personal,
            'created_by' => $this->owner->id,
            'name' => 'priya@acme.test',
            'data' => ['access_token' => 'gmail-token'],
        ]);
    };

    $this->mailbox = new FakeMailbox;
    app()->instance(MailboxFactory::class, new class($this->mailbox) extends MailboxFactory
    {
        public function __construct(private FakeMailbox $mailbox) {}

        public function for(AssistantInboxConfig $config): Mailbox
        {
            return $this->mailbox;
        }
    });

    $this->enable = function (): AssistantInboxConfig {
        ($this->givePro)();
        ($this->connectGmail)();
        Passport::actingAs($this->owner);
        $this->postJson("{$this->base}/inbox/enable")->assertSuccessful();

        return $this->assistant->inbox()->firstOrFail();
    };

    $this->classify = fn (array $labels, bool $needsReply = false) => InboxClassifierAgent::fake([[
        'labels' => collect($labels)->map(fn ($confidence, $name) => ['name' => $name, 'confidence' => $confidence])->values()->all(),
        'needs_reply' => $needsReply,
    ]]);

    $this->check = fn (AssistantInboxConfig $config): int => app(InboxProcessor::class)->check($config->refresh());
});

it('needs the plan and a connected Gmail before turning on', function () {
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/inbox/enable")->assertStatus(402);

    ($this->givePro)();
    $this->postJson("{$this->base}/inbox/enable")->assertUnprocessable();

    $this->getJson("{$this->base}/inbox")
        ->assertSuccessful()
        ->assertJsonPath('data.available.plan', true)
        ->assertJsonPath('data.available.gmail_connected', false);
});

it('turns on with five built-in labels created in Gmail', function () {
    $config = ($this->enable)();

    expect($config->enabled)->toBeTrue()
        ->and($config->labels()->pluck('name')->all())->toBe(['Needs reply', 'Time sensitive', 'Waiting on you', 'FYI', 'Low priority'])
        ->and($config->labels()->whereNull('provider_label_id')->count())->toBe(0)
        ->and(array_values($this->mailbox->labelNames))->toContain('Needs reply', 'Low priority', 'Clients');
});

it('adopts a Gmail label that already has the same name', function () {
    $this->mailbox->labelNames['Label_fyi'] = 'fyi';

    $config = ($this->enable)();

    expect($config->labels()->where('key', 'fyi')->value('provider_label_id'))->toBe('Label_fyi');
});

it('labels new mail once and keeps it in the inbox when any label says keep', function () {
    $config = ($this->enable)();
    $this->mailbox->add('m1');
    ($this->classify)(['Time sensitive' => 0.9, 'Low priority' => 0.7, 'FYI' => 0.3]);

    expect(($this->check)($config))->toBe(1);

    $record = AssistantInboxMessage::query()->sole();
    expect($record)->status->toBe(AssistantInboxMessageStatus::Classified)->archived->toBeFalse()
        ->and($record->labels)->toBe(['Time sensitive', 'Low priority'])
        ->and($this->mailbox->modified[0][2])->toBe([]);

    expect(($this->check)($config))->toBe(0);
});

it('moves mail out of the inbox only when every label says so', function () {
    $config = ($this->enable)();
    $this->mailbox->add('m1', ['isBulk' => true]);
    ($this->classify)(['Low priority' => 0.95]);

    ($this->check)($config);

    expect(AssistantInboxMessage::query()->sole()->archived)->toBeTrue()
        ->and($this->mailbox->modified[0][2])->toBe(['INBOX']);
});

it('skips mail the owner already labelled when asked to', function () {
    $config = ($this->enable)();
    $config->update(['skip_existing_labels' => true]);
    $this->mailbox->add('m1', ['labelIds' => ['INBOX', 'Label_existing']]);
    InboxClassifierAgent::fake()->preventStrayPrompts();

    ($this->check)($config);

    expect(AssistantInboxMessage::query()->sole())->status->toBe(AssistantInboxMessageStatus::Skipped);
    InboxClassifierAgent::assertNeverPrompted();
});

it('drafts a reply-all to a known sender when confident, and never sends', function () {
    $config = ($this->enable)();
    $this->mailbox->sentTo = ['sam@globex.test'];
    $this->mailbox->add('m1');
    ($this->classify)(['Needs reply' => 0.9], needsReply: true);
    ReplyDrafterAgent::fake([['should_reply' => true, 'confident' => true, 'body' => 'Hi Sam, confirming the terms. Priya']]);

    ($this->check)($config);

    $record = AssistantInboxMessage::query()->sole();
    $draft = $this->mailbox->drafts[$record->draft_provider_id];

    expect($record->draft_status)->toBe(AssistantInboxDraftStatus::Created)
        ->and($draft['to'])->toBe(['Sam <sam@globex.test>'])
        ->and($draft['cc'])->toBe(['Lee <lee@acme.test>'])
        ->and($draft['body'])->toContain('confirming the terms');

    ReplyDrafterAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Thanks Sam, sounds good.'));
});

it('keeps an unsure reply as a suggestion instead of a draft', function () {
    $config = ($this->enable)();
    $this->mailbox->sentTo = ['sam@globex.test'];
    $this->mailbox->add('m1');
    ($this->classify)(['Needs reply' => 0.9], needsReply: true);
    ReplyDrafterAgent::fake([['should_reply' => true, 'confident' => false, 'body' => 'Let me check the terms.']]);

    ($this->check)($config);

    expect(AssistantInboxMessage::query()->sole())
        ->draft_status->toBe(AssistantInboxDraftStatus::Suggested)
        ->suggestion->toBe('Let me check the terms.')
        ->draft_provider_id->toBeNull()
        ->and($this->mailbox->drafts)->toBe([]);
});

it('does not draft for unknown senders, mailers or with drafting off', function (array $mail, array $settings) {
    $config = ($this->enable)();
    $config->update($settings);
    $this->mailbox->add('m1', $mail);
    ($this->classify)(['Needs reply' => 0.9], needsReply: true);
    ReplyDrafterAgent::fake()->preventStrayPrompts();

    ($this->check)($config);

    ReplyDrafterAgent::assertNeverPrompted();
})->with([
    'unknown sender' => [[], []],
    'bulk mail' => [['isBulk' => true], ['known_senders_only' => false]],
    'no-reply sender' => [['from' => 'GitHub <noreply@github.com>'], ['known_senders_only' => false]],
    'drafting off' => [[], ['draft_mode' => 'off', 'known_senders_only' => false]],
]);

it('never overwrites a draft the owner edited, but refreshes an untouched one', function () {
    $config = ($this->enable)();
    $this->mailbox->sentTo = ['sam@globex.test'];
    $this->mailbox->add('m1');
    ($this->classify)(['Needs reply' => 0.9], needsReply: true);
    ReplyDrafterAgent::fake([
        ['should_reply' => true, 'confident' => true, 'body' => 'First draft.'],
        ['should_reply' => true, 'confident' => true, 'body' => 'Second draft.'],
        ['should_reply' => true, 'confident' => true, 'body' => 'Third draft.'],
    ]);
    ($this->check)($config);
    $draftId = AssistantInboxMessage::query()->sole()->draft_provider_id;

    // A follow-up in the same thread, draft untouched → updated in place.
    unset($this->mailbox->messages['m1']);
    $this->mailbox->add('m2', ['threadId' => 'thread_m1']);
    InboxClassifierAgent::fake([['labels' => [['name' => 'Needs reply', 'confidence' => 0.9]], 'needs_reply' => true]]);
    ($this->check)($config);
    expect($this->mailbox->drafts[$draftId]['body'])->toBe('Second draft.')
        ->and(AssistantInboxMessage::query()->where('provider_message_id', 'm2')->value('draft_status'))->toBe(AssistantInboxDraftStatus::Updated);

    // The owner edits it; the next follow-up must leave it alone.
    $this->mailbox->drafts[$draftId]['body'] = 'My own words.';
    unset($this->mailbox->messages['m2']);
    $this->mailbox->add('m3', ['threadId' => 'thread_m1']);
    InboxClassifierAgent::fake([['labels' => [['name' => 'Needs reply', 'confidence' => 0.9]], 'needs_reply' => true]]);
    ($this->check)($config);

    expect($this->mailbox->drafts[$draftId]['body'])->toBe('My own words.')
        ->and(AssistantInboxMessage::query()->where('provider_message_id', 'm3')->value('draft_status'))->toBe(AssistantInboxDraftStatus::KeptYourEdits);
});

it('switches itself off when the plan no longer includes it', function () {
    $config = ($this->enable)();
    PlanGrant::query()->delete();

    (new CheckInboxJob($config))->handle(app(InboxProcessor::class), app(InboxAccess::class));

    expect($config->refresh()->enabled)->toBeFalse()
        ->and($config->labels()->whereNotNull('provider_label_id')->count())->toBe(0);
});

it('manages labels and renames them in Gmail', function () {
    $config = ($this->enable)();

    $this->postJson("{$this->base}/inbox/labels", ['name' => 'Invoices', 'definition' => 'Bills and invoices to pay.', 'group' => 'move_out'])
        ->assertCreated();
    $this->postJson("{$this->base}/inbox/labels", ['name' => 'invoices', 'definition' => 'Dup'])->assertUnprocessable();
    $this->postJson("{$this->base}/inbox/labels", ['name' => 'A/B', 'definition' => 'x'])->assertUnprocessable();

    $invoices = $config->labels()->where('name', 'Invoices')->sole();
    $this->patchJson("{$this->base}/inbox/labels/{$invoices->id}", ['name' => 'Bills'])->assertSuccessful();
    expect($this->mailbox->labelNames[$invoices->provider_label_id])->toBe('Bills');

    $builtin = $config->labels()->where('key', 'fyi')->sole();
    $this->deleteJson("{$this->base}/inbox/labels/{$builtin->id}")->assertUnprocessable();
    $this->deleteJson("{$this->base}/inbox/labels/{$invoices->id}")->assertSuccessful();
});

it('caps labels at the limit', function () {
    config(['assistant.inbox.max_labels' => 6]);
    ($this->enable)();

    $this->postJson("{$this->base}/inbox/labels", ['name' => 'One more', 'definition' => 'x'])->assertCreated();
    $this->postJson("{$this->base}/inbox/labels", ['name' => 'Too many', 'definition' => 'x'])->assertUnprocessable();
});

it('opens a reply suggestion in chat', function () {
    $config = ($this->enable)();
    AssistantAgent::fake(['Here is a sharper version.']);
    $message = $config->messages()->create([
        'provider_message_id' => 'm1', 'status' => 'classified', 'from' => 'Sam <sam@globex.test>',
        'subject' => 'Contract renewal', 'suggestion' => 'Let me check the terms.',
    ]);

    $sessionId = $this->postJson("{$this->base}/inbox/messages/{$message->id}/accept")->assertSuccessful()->json('data.session_id');

    expect($this->assistant->sessions()->find($sessionId)->title)->toBe('Reply: Contract renewal');
    AssistantAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Let me check the terms.'));
});

it('keeps the inbox private to its owner', function () {
    $config = ($this->enable)();
    $label = $config->labels()->first();
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    Passport::actingAs($member);

    $this->patchJson("{$this->base}/inbox/labels/{$label->id}", ['definition' => 'x'])->assertNotFound();
});

it('reads Gmail messages and threads drafts into the conversation', function () {
    $credential = ($this->connectGmail)();
    $body = rtrim(strtr(base64_encode('Can you confirm by Friday?'), '+/', '-_'), '=');
    Http::fake([
        'gmail.googleapis.com/gmail/v1/users/me/messages/m1*' => Http::response([
            'id' => 'm1', 'threadId' => 't1', 'labelIds' => ['INBOX'], 'snippet' => 'Can you confirm', 'internalDate' => '1790000000000',
            'payload' => [
                'mimeType' => 'multipart/alternative',
                'headers' => [['name' => 'From', 'value' => 'Sam <sam@globex.test>'], ['name' => 'Subject', 'value' => 'Renewal'], ['name' => 'Message-ID', 'value' => '<abc@globex.test>'], ['name' => 'List-Unsubscribe', 'value' => '<mailto:x>']],
                'parts' => [['mimeType' => 'text/plain', 'body' => ['data' => $body]]],
            ],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/drafts' => Http::response(['id' => 'd1']),
    ]);
    $mailbox = new GmailMailbox($credential);

    $message = $mailbox->message('m1');
    expect($message)->body->toBe('Can you confirm by Friday?')->isBulk->toBeTrue()->threadId->toBe('t1');

    expect($mailbox->saveDraft(null, $message, ['Sam <sam@globex.test>'], [], 'Confirmed.'))->toBe('d1');

    Http::assertSent(function (HttpRequest $request): bool {
        if (! str_ends_with($request->url(), '/drafts')) {
            return false;
        }

        $raw = base64_decode(strtr($request['message']['raw'], '-_', '+/'));

        return $request['message']['threadId'] === 't1'
            && str_contains($raw, 'In-Reply-To: <abc@globex.test>')
            && str_contains($raw, 'Subject: Re: Renewal');
    });
});

it('sees a reply in a Gmail thread only from someone else and only after the given time', function () {
    $credential = ($this->connectGmail)();
    Http::fake([
        'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'Priya@acme.test']),
        'gmail.googleapis.com/gmail/v1/users/me/threads/t1*' => Http::response(['messages' => [
            ['internalDate' => '1790000000000', 'payload' => ['headers' => [['name' => 'From', 'value' => 'Priya <priya@acme.test>']]]],
            ['internalDate' => '1790000100000', 'payload' => ['headers' => [['name' => 'From', 'value' => 'Sam <sam@globex.test>']]]],
        ]]),
    ]);
    $mailbox = new GmailMailbox($credential);

    expect($mailbox->threadHasReplyAfter('t1', now()->setTimestamp(1790000050)))->toBeTrue()
        ->and($mailbox->threadHasReplyAfter('t1', now()->setTimestamp(1790000200)))->toBeFalse();
});

it('does not count the owner\'s own message as a Gmail reply', function () {
    $credential = ($this->connectGmail)();
    Http::fake([
        'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'priya@acme.test']),
        'gmail.googleapis.com/gmail/v1/users/me/threads/t1*' => Http::response(['messages' => [
            ['internalDate' => '1790000100000', 'payload' => ['headers' => [['name' => 'From', 'value' => 'Priya <priya@acme.test>']]]],
        ]]),
    ]);

    expect((new GmailMailbox($credential))->threadHasReplyAfter('t1', now()->setTimestamp(1790000000)))->toBeFalse();
});

it('sees a reply in an Outlook conversation only from someone else', function () {
    $credential = ($this->connectGmail)();
    Http::fake([
        'graph.microsoft.com/v1.0/me?*' => Http::response(['mail' => 'priya@acme.test']),
        'graph.microsoft.com/v1.0/me/messages*' => Http::response(['value' => [
            ['from' => ['emailAddress' => ['address' => 'priya@acme.test', 'name' => 'Priya']], 'receivedDateTime' => '2026-10-20T10:00:00Z'],
            ['from' => ['emailAddress' => ['address' => 'sam@globex.test', 'name' => 'Sam']], 'receivedDateTime' => '2026-10-21T10:00:00Z'],
        ]]),
    ]);
    $mailbox = new OutlookMailbox($credential);

    expect($mailbox->threadHasReplyAfter('conv1', now()->parse('2026-10-20T12:00:00Z')))->toBeTrue()
        ->and($mailbox->threadHasReplyAfter('conv1', now()->parse('2026-10-22T00:00:00Z')))->toBeFalse();
});
