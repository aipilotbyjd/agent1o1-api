<?php

use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Resend\AbstractResendNode;
use App\Nodes\Integrations\Resend\ResendAddContactNode;
use App\Nodes\Integrations\Resend\ResendCancelEmailNode;
use App\Nodes\Integrations\Resend\ResendCreateBroadcastNode;
use App\Nodes\Integrations\Resend\ResendGetContactNode;
use App\Nodes\Integrations\Resend\ResendListEmailsNode;
use App\Nodes\Integrations\Resend\ResendRescheduleEmailNode;
use App\Nodes\Integrations\Resend\ResendSendBatchNode;
use App\Nodes\Integrations\Resend\ResendSendBroadcastNode;
use App\Nodes\Integrations\Resend\ResendSendEmailNode;
use App\Services\Triggers\Push\ResendSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ResendOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function resendConfig(array $config = []): array
{
    return ['access_token' => 're_key', ...$config];
}

/**
 * Answer Resend. A key is "METHOD /path" (a trailing * takes any id); the value
 * is the JSON body (null is a 204), or a closure returning a response. A route
 * nobody answers is a 404, so a node that calls something unexpected fails the
 * test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeResend(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.resend.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.urldecode((string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['statusCode' => 404, 'name' => 'not_found', 'message' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

/**
 * @return array<string, mixed>
 */
function resendSent(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.urldecode((string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return (array) json_decode($found[0][0]->body(), true);
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function resendEmail(string $id = 'e1', string $event = 'delivered', array $extra = []): array
{
    return ['id' => $id, 'from' => 'Acme <hello@acme.com>', 'to' => ['ada@example.com'], 'subject' => 'Hi', 'last_event' => $event, 'created_at' => now()->subMinute()->toIso8601String(), 'scheduled_at' => null, ...$extra];
}

describe('resend nodes', function () {
    it('registers the nodes under the resend connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'resend_'));

        expect(count($types))->toBeGreaterThanOrEqual(19);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractResendNode::class)->and($registry->resolve($type)->category())->toBe('resend');
        }
    });

    it('sends an email with a bearer key, a named sender, tags and attachments', function () {
        fakeResend(['POST /emails' => ['id' => 'em_1']]);

        $output = (new ResendSendEmailNode)->execute(Run::factory()->create(), resendConfig([
            'sender_email' => 'hello@acme.com', 'sender_name' => 'Acme <Team>', 'to' => 'ada@example.com, bob@example.com', 'bcc' => 'ops@acme.com',
            'subject' => "Hi\r\nBcc: x@y.z", 'html' => '<p>Hello</p>', 'tags' => '{"campaign": "welcome"}', 'attachments' => ['https://x.test/files/invoice.pdf'], 'idempotency_key' => 'order-9',
        ]), []);

        expect($output)->toBe(['sent' => true, 'id' => 'em_1', 'to' => ['ada@example.com', 'bob@example.com'], 'scheduled' => false])
            ->and(resendSent('POST /emails'))->toEqual([
                'from' => 'Acme Team <hello@acme.com>',
                'to' => ['ada@example.com', 'bob@example.com'],
                'bcc' => ['ops@acme.com'],
                'subject' => 'Hi Bcc: x@y.z',
                'html' => '<p>Hello</p>',
                'attachments' => [['path' => 'https://x.test/files/invoice.pdf', 'filename' => 'invoice.pdf']],
                'tags' => [['name' => 'campaign', 'value' => 'welcome']],
            ]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer re_key') && $request->hasHeader('Idempotency-Key', 'order-9'));
    });

    it('checks what it is asked to send', function () {
        $node = new ResendSendEmailNode;
        $run = Run::factory()->create();
        $base = ['sender_email' => 'hello@acme.com', 'to' => 'ada@example.com', 'subject' => 'x', 'text' => 'y'];

        expect(fn () => $node->execute($run, resendConfig([...$base, 'text' => '']), []))->toThrow(InvalidArgumentException::class, 'HTML or plain text')
            ->and(fn () => $node->execute($run, resendConfig([...$base, 'to' => '']), []))->toThrow(InvalidArgumentException::class, 'between 1 and 50')
            ->and(fn () => $node->execute($run, resendConfig([...$base, 'to' => 'nope']), []))->toThrow(InvalidArgumentException::class, 'is not an email address')
            ->and(fn () => $node->execute($run, resendConfig([...$base, 'scheduled_at' => now()->addDays(40)->toIso8601String()]), []))->toThrow(InvalidArgumentException::class, 'within 30 days')
            ->and(fn () => $node->execute($run, resendConfig([...$base, 'tags' => '{bad']), []))->toThrow(InvalidArgumentException::class, 'valid JSON');
    });

    it('schedules an email, then reschedules and cancels it', function () {
        fakeResend(['POST /emails' => ['id' => 'em_2'], 'PATCH /emails/em_2' => ['id' => 'em_2'], 'POST /emails/em_2/cancel' => ['id' => 'em_2']]);
        $at = now()->addDay()->startOfSecond();
        $run = Run::factory()->create();

        $sent = (new ResendSendEmailNode)->execute($run, resendConfig(['sender_email' => 'hello@acme.com', 'to' => 'ada@example.com', 'subject' => 'Later', 'text' => 'Hi', 'scheduled_at' => $at->toIso8601String()]), []);
        $moved = (new ResendRescheduleEmailNode)->execute($run, resendConfig(['email_id' => 'em_2', 'scheduled_at' => $at->copy()->addHour()->toIso8601String()]), []);
        $canceled = (new ResendCancelEmailNode)->execute($run, resendConfig(['email_id' => 'em_2']), []);

        expect($sent['scheduled'])->toBeTrue()
            ->and($moved['scheduled_at'])->toBe($at->copy()->addHour()->utc()->toIso8601String())
            ->and($canceled)->toBe(['canceled' => true, 'id' => 'em_2'])
            ->and(fn () => (new ResendRescheduleEmailNode)->execute($run, resendConfig(['email_id' => 'em_2', 'scheduled_at' => '2020-01-01 10:00:00']), []))->toThrow(InvalidArgumentException::class, 'in the future');
    });

    it('sends a batch after checking each message', function () {
        fakeResend(['POST /emails/batch' => ['data' => [['id' => 'a'], ['id' => 'b']]]]);
        $node = new ResendSendBatchNode;
        $run = Run::factory()->create();
        $messages = [
            ['from' => 'hello@acme.com', 'to' => ['a@x.co'], 'subject' => 'One', 'text' => 'x'],
            ['from' => 'hello@acme.com', 'to' => 'b@x.co', 'subject' => 'Two', 'html' => '<p>x</p>'],
        ];

        expect($node->execute($run, resendConfig(['messages' => json_encode($messages)]), []))->toBe(['sent' => 2, 'ids' => ['a', 'b']])
            ->and(resendSent('POST /emails/batch'))->toBe($messages)
            ->and(fn () => $node->execute($run, resendConfig(['messages' => json_encode([['from' => 'hello@acme.com', 'to' => 'a@x.co']])]), []))->toThrow(InvalidArgumentException::class, 'Message 1 needs')
            ->and(fn () => $node->execute($run, resendConfig(['messages' => json_encode([['from' => 'f@x.co', 'to' => ['nope'], 'subject' => 's', 'text' => 't']])]), []))->toThrow(InvalidArgumentException::class, 'is not an email address')
            ->and(fn () => $node->execute($run, resendConfig(['messages' => '[]']), []))->toThrow(InvalidArgumentException::class, 'between 1 and 100');
    });

    it('filters emails by their latest event and pages by the last id', function () {
        $pages = [
            'GET /emails' => fn (Request $request) => str_contains($request->url(), 'after=e100')
                ? ['data' => [resendEmail('e101', 'bounced')], 'has_more' => false]
                : ['data' => array_map(fn (int $i): array => resendEmail("e{$i}", 'delivered'), range(1, 100)), 'has_more' => true],
        ];
        fakeResend($pages);

        $output = (new ResendListEmailsNode)->execute(Run::factory()->create(), resendConfig(['event' => 'bounced', 'limit' => 150]), []);

        expect(array_column($output['emails'], 'id'))->toBe(['e101'])->and($output['emails'][0]['status'])->toBe('bounced');
        Http::assertSentCount(2);
    });

    it('adds a contact, or updates the one already there', function () {
        fakeResend([
            'GET /audiences/aud1/contacts/ada@example.com' => fn () => Http::response(['statusCode' => 404, 'name' => 'not_found', 'message' => 'Contact not found'], 404),
            'POST /audiences/aud1/contacts' => ['id' => 'c1'],
        ]);
        $node = new ResendAddContactNode;

        $created = $node->execute(Run::factory()->create(), resendConfig(['audience_id' => 'aud1', 'email' => ' Ada@Example.com ', 'first_name' => 'Ada', 'unsubscribed' => 'subscribed']), []);

        expect($created)->toBe(['id' => 'c1', 'email' => 'ada@example.com', 'created' => true])
            ->and(resendSent('POST /audiences/aud1/contacts'))->toBe(['email' => 'ada@example.com', 'first_name' => 'Ada', 'unsubscribed' => false]);

        fakeResend(['GET /audiences/aud1/contacts/ada@example.com' => ['id' => 'c1', 'email' => 'ada@example.com'], 'PATCH /audiences/aud1/contacts/ada@example.com' => ['id' => 'c1']]);

        expect($node->execute(Run::factory()->create(), resendConfig(['audience_id' => 'aud1', 'email' => 'ada@example.com', 'last_name' => 'Lovelace']), []))->toBe(['id' => 'c1', 'email' => 'ada@example.com', 'created' => false])
            ->and(resendSent('PATCH /audiences/aud1/contacts/ada@example.com'))->toBe(['last_name' => 'Lovelace']);
    });

    it('says when a contact is not there instead of failing', function () {
        fakeResend(['GET /audiences/aud1/contacts/*' => fn () => Http::response(['statusCode' => 404, 'name' => 'not_found', 'message' => 'Contact not found'], 404)]);

        expect((new ResendGetContactNode)->execute(Run::factory()->create(), resendConfig(['audience_id' => 'aud1', 'contact' => 'ada@example.com']), []))->toBe(['found' => false, 'contact' => 'ada@example.com']);
    });

    it('writes a broadcast and sends it now or at a time', function () {
        fakeResend(['POST /broadcasts' => ['id' => 'b1'], 'POST /broadcasts/b1/send' => ['id' => 'b1']]);
        $run = Run::factory()->create();

        $created = (new ResendCreateBroadcastNode)->execute($run, resendConfig(['audience_id' => 'aud1', 'sender_email' => 'hello@acme.com', 'subject' => 'News', 'html' => '<p>Hi {{{FIRST_NAME}}}</p>', 'send_now' => true]), []);
        $later = (new ResendSendBroadcastNode)->execute($run, resendConfig(['broadcast_id' => 'b1', 'scheduled_at' => now()->addDay()->toIso8601String()]), []);

        expect($created)->toBe(['id' => 'b1', 'sent' => true])->and($later['sent'])->toBeTrue()
            ->and(fn () => (new ResendCreateBroadcastNode)->execute($run, resendConfig(['audience_id' => 'aud1', 'sender_email' => 'hello@acme.com', 'subject' => 'News']), []))->toThrow(InvalidArgumentException::class, 'HTML or plain text')
            ->and(fn () => (new ResendSendBroadcastNode)->execute($run, resendConfig(['broadcast_id' => 'b1', 'scheduled_at' => '2020-01-01 10:00:00']), []))->toThrow(InvalidArgumentException::class, 'in the future');
    });

    it('says what Resend said when it refuses', function () {
        fakeResend(['POST /emails' => fn () => Http::response(['statusCode' => 403, 'name' => 'validation_error', 'message' => 'The acme.com domain is not verified.'], 403)]);

        expect(fn () => (new ResendSendEmailNode)->execute(Run::factory()->create(), resendConfig(['sender_email' => 'hello@acme.com', 'to' => 'a@b.co', 'subject' => 'x', 'text' => 'y']), []))
            ->toThrow(RuntimeException::class, 'Resend error: The acme.com domain is not verified. (validation_error)');
    });
});

describe('resend dropdowns', function () {
    it('lists audiences and domains with their status', function () {
        fakeResend(['GET /audiences' => ['data' => [['id' => 'aud1', 'name' => 'Newsletter']]], 'GET /domains' => ['data' => [['id' => 'd1', 'name' => 'acme.com', 'status' => 'verified']]]]);
        $query = new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), [], 're_key');
        $source = app(ResendOptions::class);

        expect($source->load('resend.audiences', $query)->toArray()['options'])->toBe([['value' => 'aud1', 'label' => 'Newsletter']])
            ->and($source->load('resend.domains', $query)->toArray()['options'])->toBe([['value' => 'd1', 'label' => 'acme.com', 'description' => 'verified']]);
    });
});

/**
 * A published workflow whose first step is a Resend reader switched on as a
 * trigger, with its subscription synced against a faked Resend.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 */
function resendTrigger(string $nodeType = 'resend_list_emails', array $nodeConfig = [], array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeResend($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'resend') ?? Connector::factory()->create(['key' => 'resend']))
        ->create(['data' => ['api_key' => 're_key'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();

    if ($sync) {
        SyncTriggerSubscription::dispatchSync($trigger->id);
    }

    return $trigger->fresh();
}

describe('resend triggers', function () {
    it('polls emails that reach an event: a baseline first, then one run for each', function () {
        $trigger = resendTrigger(nodeConfig: ['event' => 'bounced'], sync: false);
        $poll = fn (array $emails) => fakeResend(['GET /emails' => ['data' => $emails, 'has_more' => false]]);

        $poll([resendEmail('e1', 'bounced')]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([resendEmail('e3', 'bounced'), resendEmail('e2', 'delivered'), resendEmail('e1', 'bounced')]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->all())->toBe(['e3']);
    });

    it('counts an email that moves on as a new event', function () {
        $node = app(NodeRegistry::class)->resolve('resend_list_emails');

        expect($node->triggerItemId(['id' => 'e1', 'status' => 'delivered']))->not->toBe($node->triggerItemId(['id' => 'e1', 'status' => 'opened']));
    });
});

describe('resend instant delivery', function () {
    it('registers a webhook for the chosen event with a token URL, and starts a poll when it arrives', function () {
        $trigger = resendTrigger(nodeConfig: ['event' => 'bounced'], routes: ['POST /webhooks' => ['id' => 'wh_1']]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('email.bounced')
            ->and($watch->external_id)->toBe('wh_1')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(resendSent('POST /webhooks'))->toBe(['endpoint' => url('/api/hooks/apps/resend').'?token='.$watch->secret, 'events' => ['email.bounced']]);

        test()->postJson('/api/hooks/apps/resend?token='.$watch->secret, ['type' => 'email.bounced', 'data' => ['email_id' => 'e1']], ['svix-id' => 'msg_1'])->assertOk();
        test()->postJson('/api/hooks/apps/resend?token='.$watch->secret, ['type' => 'email.bounced', 'data' => ['email_id' => 'e1']], ['svix-id' => 'msg_1'])->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('listens to every email event for "any"', function () {
        resendTrigger(routes: ['POST /webhooks' => ['id' => 'wh_2']]);

        expect(ProviderWatch::query()->sole()->resource_key)->toBe('email.any')
            ->and(resendSent('POST /webhooks')['events'])->toContain('email.sent', 'email.delivered', 'email.bounced', 'email.complained', 'email.opened', 'email.clicked');
    });

    it('listens to new contacts for the contact trigger', function () {
        resendTrigger('resend_list_contacts', ['audience_id' => 'aud1'], ['POST /webhooks' => ['id' => 'wh_3']]);

        expect(ProviderWatch::query()->sole()->resource_key)->toBe('contact.created');
    });

    it('refuses a wrong or missing token and is closed while the switch is off', function () {
        resendTrigger(routes: ['POST /webhooks' => ['id' => 'wh_1']]);
        $watch = ProviderWatch::query()->sole();

        test()->postJson('/api/hooks/apps/resend?token=nope', ['type' => 'email.sent'])->assertUnauthorized();
        test()->postJson('/api/hooks/apps/resend', ['type' => 'email.sent'])->assertUnauthorized();
        config(['triggers.push.resend.enabled' => false]);
        test()->postJson('/api/hooks/apps/resend?token='.$watch->secret, ['type' => 'email.sent'])->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('says so when Resend refuses the webhook, and drops the watch', function () {
        $trigger = resendTrigger(routes: ['POST /webhooks' => fn () => Http::response(['statusCode' => 422, 'name' => 'validation_error', 'message' => 'Invalid endpoint'], 422)]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)->and($trigger->subscription->last_error)->toContain('422')->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('makes the webhook again when Resend disabled or lost it, and removes it with the watch', function () {
        resendTrigger(routes: ['POST /webhooks' => ['id' => 'wh_1']]);
        $watch = ProviderWatch::query()->sole();
        $source = app(ResendSource::class);

        fakeResend(['GET /webhooks/wh_1' => ['id' => 'wh_1', 'status' => 'disabled'], 'POST /webhooks' => ['id' => 'wh_9']]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('wh_9');

        fakeResend(['GET /webhooks/wh_9' => ['id' => 'wh_9', 'status' => 'enabled']]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

        fakeResend(['DELETE /webhooks/wh_9' => null]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
