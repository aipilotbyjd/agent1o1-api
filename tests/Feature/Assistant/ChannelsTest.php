<?php

use App\Ai\Assistant\AssistantAgent;
use App\Enums\Assistant\AssistantSessionOrigin;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Jobs\Assistant\HandleSlackMessageJob;
use App\Mail\Assistant\AssistantEmailReply;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantSlackInstall;
use App\Models\User;
use App\Services\Assistant\Branding\BrandRepository;
use App\Services\Assistant\Channels\SlackChannel;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;

beforeEach(function () {
    config([
        'assistant.brand.email_local_part' => 'helper',
        'assistant.brand.inbound_domain' => 'mail.example.test',
        'assistant.channels.email.inbound_token' => 'inbound-secret',
        'assistant.channels.slack.client_id' => 'slack-client',
        'assistant.channels.slack.client_secret' => 'slack-secret',
        'assistant.channels.slack.signing_secret' => 'signing-secret',
        'app.frontend_url' => 'https://app.example.test',
    ]);

    $this->owner = User::factory()->create(['email' => 'owner@example.test']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);

    $this->email = fn (array $overrides = []): array => [
        'FromFull' => ['Email' => 'Owner@Example.test', 'Name' => 'Owner'],
        'ToFull' => [['Email' => 'helper@mail.example.test']],
        'Subject' => 'Plan my week',
        'MessageID' => 'abc-1',
        'TextBody' => 'What is on my calendar?',
        'Headers' => [
            ['Name' => 'Message-ID', 'Value' => '<abc-1@example.test>'],
            ['Name' => 'Authentication-Results', 'Value' => 'mx.example; dkim=pass header.d=example.test'],
        ],
        ...$overrides,
    ];

    $this->postEmail = fn (array $payload, string $token = 'inbound-secret') => $this->postJson("/api/hooks/assistant/email?token={$token}", $payload);

    $this->slackInstall = fn (): AssistantSlackInstall => AssistantSlackInstall::query()->create([
        'slack_team_id' => 'T1', 'team_name' => 'Acme Slack', 'bot_token' => 'xoxb-test',
        'bot_user_id' => 'UBOT', 'workspace_id' => $this->workspace->id, 'installed_by' => $this->owner->id,
    ]);

    $this->signedSlackPost = function (array $payload, ?int $timestamp = null) {
        $body = json_encode($payload);
        $timestamp ??= time();
        $signature = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", 'signing-secret');

        return $this->call('POST', '/api/hooks/assistant/slack/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_SLACK_SIGNATURE' => $signature,
        ], $body);
    };
});

it('turns an email from a member into a conversation and replies in the thread', function () {
    Mail::fake();
    AssistantAgent::fake(['You have two meetings tomorrow.']);

    ($this->postEmail)(($this->email)())->assertOk()->assertJson(['result' => 'accepted']);

    $session = AssistantSession::query()->sole();
    expect($session)->origin->toBe(AssistantSessionOrigin::Email)->title->toBe('Plan my week')
        ->and($session->turns()->sole()->status)->toBe(AssistantTurnStatus::Completed);

    AssistantAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'What is on my calendar?'));

    Mail::assertSent(AssistantEmailReply::class, function (AssistantEmailReply $mail): bool {
        return $mail->hasTo('Owner@Example.test')
            && $mail->body === 'You have two meetings tomorrow.'
            && $mail->envelope()->subject === 'Re: Plan my week'
            && $mail->envelope()->from->address === 'helper@mail.example.test'
            && $mail->inReplyTo === '<abc-1@example.test>';
    });
});

it('keeps replies in an email thread in the same conversation', function () {
    Mail::fake();
    AssistantAgent::fake(['First answer.', 'Second answer.']);

    ($this->postEmail)(($this->email)())->assertOk();
    ($this->postEmail)(($this->email)([
        'Subject' => 'Re: Plan my week',
        'MessageID' => 'abc-2',
        'StrippedTextReply' => 'And on Friday?',
        'Headers' => [
            ['Name' => 'Message-ID', 'Value' => '<abc-2@example.test>'],
            ['Name' => 'In-Reply-To', 'Value' => '<reply-from-assistant@mail.example.test>'],
            ['Name' => 'References', 'Value' => '<abc-1@example.test> <reply-from-assistant@mail.example.test>'],
            ['Name' => 'Received-SPF', 'Value' => 'Pass (sender SPF authorized)'],
        ],
    ]))->assertOk();

    $session = AssistantSession::query()->sole();
    expect($session->turns()->count())->toBe(2)
        ->and($session->channel_context['in_reply_to'])->toBe('<abc-2@example.test>');
});

it('ignores email from strangers, unauthenticated senders and other addresses', function (array $overrides) {
    Mail::fake();

    ($this->postEmail)(($this->email)($overrides))->assertOk()->assertJsonPath('result', fn (string $result) => str_starts_with($result, 'ignored'));

    expect(AssistantSession::query()->count())->toBe(0);
    Mail::assertNothingSent();
})->with([
    'stranger' => [['FromFull' => ['Email' => 'someone@else.test']]],
    'failed SPF and DKIM' => [['Headers' => [['Name' => 'Authentication-Results', 'Value' => 'dkim=fail; spf=softfail']]]],
    'not addressed to the assistant' => [['ToFull' => [['Email' => 'sales@mail.example.test']]]],
]);

it('ignores email from an unverified account', function () {
    $this->owner->forceFill(['email_verified_at' => null])->save();

    ($this->postEmail)(($this->email)())->assertJson(['result' => 'ignored: unknown sender']);
});

it('rejects inbound email without the secret token', function () {
    ($this->postEmail)(($this->email)(), 'wrong')->assertNotFound();

    expect(AssistantSession::query()->count())->toBe(0);
});

it('answers the Slack URL check and rejects bad signatures', function () {
    ($this->signedSlackPost)(['type' => 'url_verification', 'challenge' => 'xyz'])->assertOk()->assertJson(['challenge' => 'xyz']);

    $this->postJson('/api/hooks/assistant/slack/events', ['type' => 'url_verification', 'challenge' => 'xyz'], [
        'X-Slack-Request-Timestamp' => (string) time(), 'X-Slack-Signature' => 'v0=forged',
    ])->assertUnauthorized();

    ($this->signedSlackPost)(['type' => 'url_verification', 'challenge' => 'xyz'], time() - 600)->assertUnauthorized();
});

it('queues each Slack DM once and skips the bot\'s own messages', function () {
    Queue::fake();
    $event = fn (array $data = []): array => [
        'type' => 'event_callback', 'team_id' => 'T1', 'event_id' => 'Ev1',
        'event' => ['type' => 'message', 'channel_type' => 'im', 'user' => 'U1', 'channel' => 'D1', 'ts' => '100.1', 'text' => 'hi', ...$data],
    ];

    ($this->signedSlackPost)($event())->assertOk();
    ($this->signedSlackPost)($event())->assertOk();
    ($this->signedSlackPost)([...$event(['bot_id' => 'B1']), 'event_id' => 'Ev2'])->assertOk();
    ($this->signedSlackPost)([...$event(['subtype' => 'message_changed']), 'event_id' => 'Ev3'])->assertOk();
    ($this->signedSlackPost)([...$event(['channel_type' => 'channel']), 'event_id' => 'Ev4'])->assertOk();

    Queue::assertPushed(HandleSlackMessageJob::class, 1);
});

it('answers a Slack DM in its thread', function () {
    ($this->slackInstall)();
    AssistantAgent::fake(['**Done**: see [the doc](https://example.test/doc).']);
    Http::fake([
        'slack.com/api/users.info*' => Http::response(['ok' => true, 'user' => ['profile' => ['email' => 'owner@example.test']]]),
        'slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
    ]);

    (new HandleSlackMessageJob('T1', ['type' => 'message', 'user' => 'U1', 'channel' => 'D1', 'ts' => '100.1', 'text' => 'Summarise the doc']))->handle(app(SlackChannel::class));

    $session = AssistantSession::query()->sole();
    expect($session)->origin->toBe(AssistantSessionOrigin::Slack)->external_thread_ref->toBe('T1:D1:100.1');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage')
        && $request['channel'] === 'D1'
        && $request['thread_ts'] === '100.1'
        && $request['text'] === '*Done*: see <https://example.test/doc|the doc>.'
        && $request->hasHeader('Authorization', 'Bearer xoxb-test'));
});

it('tells unknown Slack users how to get access', function () {
    ($this->slackInstall)();
    Http::fake([
        'slack.com/api/users.info*' => Http::response(['ok' => true, 'user' => ['profile' => ['email' => 'nobody@else.test']]]),
        'slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
    ]);

    app(SlackChannel::class)->handle('T1', ['user' => 'U9', 'channel' => 'D9', 'ts' => '1.1', 'text' => 'hi']);

    expect(AssistantSession::query()->count())->toBe(0);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_contains($request['text'], "couldn't match"));
});

it('shows channel status and builds a signed Slack install link', function () {
    Passport::actingAs($this->owner);
    $base = "/api/v1/workspaces/{$this->workspace->id}/assistant/channels";

    $this->getJson($base)->assertOk()
        ->assertJsonPath('data.email.address', 'helper@mail.example.test')
        ->assertJsonPath('data.slack.available', true)
        ->assertJsonPath('data.slack.installed', false);

    $url = $this->postJson("{$base}/slack/install")->assertOk()->json('data.url');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://slack.com/oauth/v2/authorize')
        ->and($query['client_id'])->toBe('slack-client')
        ->and(Crypt::decrypt($query['state']))->toMatchArray(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
});

it('stores the bot token when an admin finishes Add to Slack', function () {
    Http::fake(['slack.com/api/oauth.v2.access' => Http::response([
        'ok' => true, 'access_token' => 'xoxb-new', 'bot_user_id' => 'UBOT', 'team' => ['id' => 'T7', 'name' => 'Acme Slack'],
    ])]);
    $state = Crypt::encrypt(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id, 'expires' => now()->addMinutes(5)->getTimestamp()]);

    $this->get('/api/hooks/assistant/slack/oauth?'.http_build_query(['code' => 'c1', 'state' => $state]))
        ->assertRedirect("https://app.example.test/{$this->workspace->id}/assistant?slack=connected");

    $install = AssistantSlackInstall::query()->sole();
    expect($install)->slack_team_id->toBe('T7')->bot_token->toBe('xoxb-new')->workspace_id->toBe($this->workspace->id);
});

it('refuses expired or forged Slack install links', function () {
    $expired = Crypt::encrypt(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id, 'expires' => now()->subMinute()->getTimestamp()]);

    $this->get('/api/hooks/assistant/slack/oauth?'.http_build_query(['code' => 'c1', 'state' => $expired]))->assertStatus(400);
    $this->get('/api/hooks/assistant/slack/oauth?code=c1&state=forged')->assertStatus(400);

    expect(AssistantSlackInstall::query()->count())->toBe(0);
});

it('threads the reply mail under the owner\'s message', function () {
    config(['mail.default' => 'array']);
    $brand = app(BrandRepository::class)->current();

    Mail::to('owner@example.test')->send(new AssistantEmailReply($brand, 'Plan my week', 'Hello', '<abc-2@example.test>', '<abc-1@example.test> <abc-2@example.test>'));

    $headers = app('mailer')->getSymfonyTransport()->messages()->sole()->getOriginalMessage()->getHeaders();

    expect($headers->get('In-Reply-To')->getBodyAsString())->toBe('<abc-2@example.test>')
        ->and($headers->get('References')->getBodyAsString())->toBe('<abc-1@example.test> <abc-2@example.test>')
        ->and($headers->get('From')->getBodyAsString())->toContain('helper@mail.example.test');
});
