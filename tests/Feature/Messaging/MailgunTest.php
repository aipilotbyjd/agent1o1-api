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
use App\Nodes\Integrations\Mailgun\AbstractMailgunNode;
use App\Nodes\Integrations\Mailgun\MailgunAddMemberNode;
use App\Nodes\Integrations\Mailgun\MailgunAddSuppressionNode;
use App\Nodes\Integrations\Mailgun\MailgunGetMemberNode;
use App\Nodes\Integrations\Mailgun\MailgunGetStatsNode;
use App\Nodes\Integrations\Mailgun\MailgunListEventsNode;
use App\Nodes\Integrations\Mailgun\MailgunListMembersNode;
use App\Nodes\Integrations\Mailgun\MailgunRemoveSuppressionNode;
use App\Nodes\Integrations\Mailgun\MailgunSendEmailNode;
use App\Nodes\Integrations\Mailgun\MailgunUpdateMemberNode;
use App\Services\Triggers\Push\MailgunSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\MailgunOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * A run with a connected Mailgun account in its workspace.
 *
 * @param  array<string, mixed>  $data
 */
function mailgunRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'mailgun') ?? Connector::factory()->create(['key' => 'mailgun']))->id,
        'data' => ['api_key' => 'key-mg', 'domain' => 'mg.example.com', ...$data],
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Mailgun on either host. A key is "METHOD /path" (a trailing * takes any
 * id); the value is the JSON body (null is a 204), or a closure returning a
 * response. A route nobody answers is a 404, so a node that calls something
 * unexpected fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeMailgun(array $routes): void
{
    Http::swap(new Factory);
    $answer = function (Request $request) use ($routes) {
        $key = $request->method().' '.urldecode((string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['message' => "Nothing fakes {$key}"], 404);
        }

        $value = value($routes[$route], $request);

        return $value instanceof GuzzleHttp\Promise\PromiseInterface ? $value : ($value === null ? Http::response(null, 204) : Http::response($value));
    };
    Http::fake(['api.mailgun.net/*' => $answer, 'api.eu.mailgun.net/*' => $answer]);
}

/**
 * The form a call sent, as a list of name/value pairs (a repeated name appears more than once).
 *
 * @return list<array{0: string, 1: string}>
 */
function mailgunForm(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.urldecode((string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return array_map(fn (string $pair): array => array_map('urldecode', explode('=', $pair, 2)), array_values(array_filter(explode('&', $found[0][0]->body()))));
}

/**
 * The values sent under one name.
 *
 * @param  list<array{0: string, 1: string}>  $form
 * @return list<string>
 */
function mailgunField(array $form, string $name): array
{
    return array_values(array_map(fn (array $pair): string => $pair[1], array_filter($form, fn (array $pair): bool => $pair[0] === $name)));
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function mailgunEvent(string $id = 'ev1', string $event = 'delivered', array $extra = []): array
{
    return ['id' => $id, 'event' => $event, 'timestamp' => 1772100000.5, 'recipient' => 'ada@example.com', 'message' => ['headers' => ['message-id' => 'mid1', 'subject' => 'Hi', 'from' => 'hello@mg.example.com', 'to' => 'ada@example.com']], 'tags' => ['welcome'], 'user-variables' => ['order' => '1001'], ...$extra];
}

describe('mailgun nodes', function () {
    it('registers the nodes under the mailgun connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'mailgun_'));

        expect(count($types))->toBeGreaterThanOrEqual(20);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractMailgunNode::class)->and($registry->resolve($type)->category())->toBe('mailgun');
        }
    });

    it('sends an email to the connection\'s domain with basic auth, repeated recipients and tags', function () {
        fakeMailgun(['POST /v3/mg.example.com/messages' => ['id' => '<20260301@mg.example.com>', 'message' => 'Queued. Thank you.']]);

        $output = (new MailgunSendEmailNode)->execute(mailgunRun(), ['sender_email' => 'hello@mg.example.com', 'sender_name' => 'Acme <Team>', 'to' => 'ada@example.com, bob@example.com', 'subject' => "Hi\r\nBcc: x@y.z", 'html' => '<p>Hello</p>', 'tags' => 'welcome, news', 'track_opens' => 'yes', 'variables' => '{"order": "1001"}', 'reply_to' => 'support@example.com'], []);
        $form = mailgunForm('POST /v3/mg.example.com/messages');

        expect($output)->toBe(['sent' => true, 'id' => '<20260301@mg.example.com>', 'message' => 'Queued. Thank you.', 'to' => ['ada@example.com', 'bob@example.com'], 'scheduled' => false])
            ->and(mailgunField($form, 'from'))->toBe(['Acme Team <hello@mg.example.com>'])
            ->and(mailgunField($form, 'to'))->toBe(['ada@example.com', 'bob@example.com'])
            ->and(mailgunField($form, 'subject'))->toBe(['Hi Bcc: x@y.z'])
            ->and(mailgunField($form, 'o:tag'))->toBe(['welcome', 'news'])
            ->and(mailgunField($form, 'o:tracking-opens'))->toBe(['yes'])
            ->and(mailgunField($form, 'v:order'))->toBe(['1001'])
            ->and(mailgunField($form, 'h:Reply-To'))->toBe(['support@example.com'])
            ->and(mailgunField($form, 'recipient-variables'))->toBe([]);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.mailgun.net/') && $request->hasHeader('Authorization', 'Basic '.base64_encode('api:key-mg')));
    });

    it('uses the EU host for an EU domain, and a template with its values', function () {
        fakeMailgun(['POST /v3/mg.example.com/messages' => ['id' => 'x']]);

        (new MailgunSendEmailNode)->execute(mailgunRun(['region' => 'eu']), ['sender_email' => 'hello@mg.example.com', 'to' => 'ada@example.com', 'template' => 'welcome', 'template_variables' => '{"name": "Ada"}', 'one_each' => true], []);
        $form = mailgunForm('POST /v3/mg.example.com/messages');

        expect(mailgunField($form, 'template'))->toBe(['welcome'])
            ->and(json_decode(mailgunField($form, 'h:X-Mailgun-Variables')[0], true))->toBe(['name' => 'Ada'])
            ->and(json_decode(mailgunField($form, 'recipient-variables')[0], true))->toBe(['ada@example.com' => []]);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.eu.mailgun.net/'));
    });

    it('checks what it is asked to send, and schedules within seven days', function () {
        $node = new MailgunSendEmailNode;
        $run = mailgunRun();
        $base = ['sender_email' => 'hello@mg.example.com', 'to' => 'ada@example.com', 'subject' => 'x', 'text' => 'y'];

        expect(fn () => $node->execute($run, [...$base, 'to' => ''], []))->toThrow(InvalidArgumentException::class, 'between 1 and 1000')
            ->and(fn () => $node->execute($run, [...$base, 'text' => ''], []))->toThrow(InvalidArgumentException::class, 'a subject and some content or a template')
            ->and(fn () => $node->execute($run, [...$base, 'to' => 'nope'], []))->toThrow(InvalidArgumentException::class, 'is not an email address')
            ->and(fn () => $node->execute($run, [...$base, 'deliver_at' => now()->addDays(10)->toIso8601String()], []))->toThrow(InvalidArgumentException::class, 'within 7 days')
            ->and(fn () => $node->execute(mailgunRun(['domain' => 'bad domain/../x']), $base, []))->toThrow(InvalidArgumentException::class, 'sending domain');

        fakeMailgun(['POST /v3/mg.example.com/messages' => ['id' => 'x']]);
        $at = now()->addDay()->startOfSecond();
        $output = $node->execute($run, [...$base, 'deliver_at' => $at->toIso8601String()], []);

        expect($output['scheduled'])->toBeTrue()->and(mailgunField(mailgunForm('POST /v3/mg.example.com/messages'), 'o:deliverytime'))->toBe([$at->utc()->toRfc7231String()]);
    });

    it('lists events newest first, mapping a failure kind to a severity and a time to the end of the range', function () {
        fakeMailgun(['GET /v3/mg.example.com/events' => ['items' => [mailgunEvent('ev2', 'failed', ['severity' => 'permanent', 'reason' => 'bounce', 'delivery-status' => ['code' => 550, 'message' => 'No such user']]), mailgunEvent('ev1', 'failed', ['severity' => 'permanent'])], 'paging' => ['next' => '']]]);

        $output = (new MailgunListEventsNode)->execute(mailgunRun(), ['event' => 'permanent_fail', 'since' => '2026-03-01T00:00:00Z', 'recipient' => 'ada@example.com'], []);

        expect($output['events'][0])->toMatchArray(['id' => 'ev2', 'event' => 'failed', 'severity' => 'permanent', 'recipient' => 'ada@example.com', 'subject' => 'Hi', 'message_id' => 'mid1', 'status_code' => 550, 'status_message' => 'No such user', 'at' => '2026-02-26T10:00:00+00:00', 'tags' => ['welcome']]);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'event=failed') && str_contains($request->url(), 'severity=permanent') && str_contains($request->url(), 'ascending=no') && str_contains($request->url(), 'end='.Illuminate\Support\Carbon::parse('2026-03-01T00:00:00Z')->timestamp));
    });

    it('follows the paging link, but only on the Mailgun host', function () {
        fakeMailgun([
            'GET /v3/mg.example.com/events' => ['items' => [mailgunEvent('ev1')], 'paging' => ['next' => 'https://api.mailgun.net/v3/mg.example.com/events/page2']],
            'GET /v3/mg.example.com/events/page2' => ['items' => [mailgunEvent('ev2')], 'paging' => ['next' => 'https://api.mailgun.net/v3/mg.example.com/events/page3']],
            'GET /v3/mg.example.com/events/page3' => ['items' => []],
        ]);

        expect((new MailgunListEventsNode)->execute(mailgunRun(), ['limit' => 10], [])['count'])->toBe(2);

        fakeMailgun(['GET /v3/mg.example.com/events' => ['items' => [mailgunEvent('ev1')], 'paging' => ['next' => 'https://evil.example.com/steal']]]);

        expect(fn () => (new MailgunListEventsNode)->execute(mailgunRun(), ['limit' => 10], []))->toThrow(RuntimeException::class, 'unexpected address');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'evil.example.com'));
    });

    it('adds up the stats, counting both kinds of failure', function () {
        fakeMailgun(['GET /v3/mg.example.com/stats/total' => ['stats' => [
            ['time' => 'Sun, 01 Mar 2026 00:00:00 UTC', 'accepted' => ['total' => 10], 'delivered' => ['total' => 8], 'failed' => ['permanent' => ['total' => 1], 'temporary' => ['total' => 2]], 'opened' => ['total' => 4]],
            ['time' => 'Mon, 02 Mar 2026 00:00:00 UTC', 'accepted' => ['total' => 5], 'delivered' => ['total' => 5], 'failed' => ['permanent' => ['total' => 0], 'temporary' => ['total' => 0]], 'opened' => ['total' => 1]],
        ]]]);
        $node = new MailgunGetStatsNode;

        $output = $node->execute(mailgunRun(), ['start_date' => '2026-03-01', 'end_date' => '2026-03-02'], []);

        expect((array) $output['totals'])->toMatchArray(['accepted' => 15, 'delivered' => 13, 'failed' => 3, 'opened' => 5])
            ->and(fn () => $node->execute(mailgunRun(), ['start_date' => '2026-03-05', 'end_date' => '2026-03-01'], []))->toThrow(InvalidArgumentException::class, 'before');
        Http::assertSent(fn (Request $request): bool => substr_count($request->url(), 'event=') === 7 && str_contains($request->url(), 'resolution=day'));
    });

    it('adds a member with an upsert, reads one, and says when someone is not on the list', function () {
        fakeMailgun(['POST /v3/lists/news@mg.example.com/members' => ['member' => ['address' => 'ada@example.com', 'name' => 'Ada', 'subscribed' => true, 'vars' => ['plan' => 'pro']]]]);

        $added = (new MailgunAddMemberNode)->execute(mailgunRun(), ['list' => 'news@mg.example.com', 'email' => ' Ada@Example.com ', 'name' => 'Ada', 'vars' => '{"plan": "pro"}'], []);
        $form = mailgunForm('POST /v3/lists/news@mg.example.com/members');

        expect($added)->toMatchArray(['email' => 'ada@example.com', 'name' => 'Ada', 'subscribed' => true])
            ->and(mailgunField($form, 'upsert'))->toBe(['yes'])
            ->and(mailgunField($form, 'subscribed'))->toBe(['yes'])
            ->and(json_decode(mailgunField($form, 'vars')[0], true))->toBe(['plan' => 'pro']);

        fakeMailgun(['GET /v3/lists/news@mg.example.com/members/ada@example.com' => fn () => Http::response(['message' => 'Address not found in mailing list'], 404)]);

        expect((new MailgunGetMemberNode)->execute(mailgunRun(), ['list' => 'news@mg.example.com', 'email' => 'ada@example.com'], []))->toBe(['found' => false, 'email' => 'ada@example.com']);
    });

    it('updates only what changes, and lists subscribed members', function () {
        fakeMailgun(['PUT /v3/lists/news@mg.example.com/members/ada@example.com' => ['member' => ['address' => 'ada@example.com', 'subscribed' => false]], 'GET /v3/lists/news@mg.example.com/members/pages' => ['items' => [['address' => 'ada@example.com', 'subscribed' => true, 'vars' => []]], 'paging' => ['next' => '']]]);
        $node = new MailgunUpdateMemberNode;
        $run = mailgunRun();

        $node->execute($run, ['list' => 'news@mg.example.com', 'email' => 'ada@example.com', 'subscribed' => 'no'], []);
        expect(mailgunForm('PUT /v3/lists/news@mg.example.com/members/ada@example.com'))->toBe([['subscribed', 'no']])
            ->and(fn () => $node->execute($run, ['list' => 'news@mg.example.com', 'email' => 'ada@example.com'], []))->toThrow(InvalidArgumentException::class, 'Change at least one thing')
            ->and(fn () => $node->execute($run, ['list' => 'not-a-list', 'email' => 'ada@example.com', 'name' => 'x'], []))->toThrow(InvalidArgumentException::class, 'is not an email address');

        $members = (new MailgunListMembersNode)->execute($run, ['list' => 'news@mg.example.com', 'subscribed' => 'yes'], []);

        expect($members['count'])->toBe(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'subscribed=yes'));
    });

    it('suppresses an address as an unsubscribe, bounce or complaint, and lifts one', function () {
        fakeMailgun(['POST /v3/mg.example.com/unsubscribes' => ['message' => 'Address has been added'], 'POST /v3/mg.example.com/bounces' => ['message' => 'ok'], 'DELETE /v3/mg.example.com/bounces/ada@example.com' => ['message' => 'removed']]);
        $run = mailgunRun();

        (new MailgunAddSuppressionNode)->execute($run, ['email' => 'Ada@Example.com', 'kind' => 'unsubscribes'], []);
        expect(mailgunForm('POST /v3/mg.example.com/unsubscribes'))->toBe([['address', 'ada@example.com'], ['tag', '*']]);

        (new MailgunAddSuppressionNode)->execute($run, ['email' => 'ada@example.com', 'kind' => 'bounces', 'reason' => 'Mailbox full'], []);
        expect(mailgunForm('POST /v3/mg.example.com/bounces'))->toBe([['address', 'ada@example.com'], ['error', 'Mailbox full']])
            ->and((new MailgunRemoveSuppressionNode)->execute($run, ['email' => 'ada@example.com', 'kind' => 'bounces'], []))->toBe(['removed' => true, 'found' => true, 'email' => 'ada@example.com', 'kind' => 'bounces'])
            ->and(fn () => (new MailgunRemoveSuppressionNode)->execute($run, ['email' => 'ada@example.com', 'kind' => '../x'], []))->toThrow(InvalidArgumentException::class, 'not a suppression list');
    });

    it('says what Mailgun said when it refuses', function () {
        fakeMailgun(['POST /v3/mg.example.com/messages' => fn () => Http::response(['message' => 'Sandbox subdomains are for test purposes only.'], 403)]);

        expect(fn () => (new MailgunSendEmailNode)->execute(mailgunRun(), ['sender_email' => 'hello@mg.example.com', 'to' => 'a@b.co', 'subject' => 'x', 'text' => 'y'], []))
            ->toThrow(RuntimeException::class, 'Mailgun error: Sandbox subdomains are for test purposes only.');
    });
});

describe('mailgun dropdowns', function () {
    it('lists mailing lists with their size and templates of the connection\'s domain', function () {
        fakeMailgun([
            'GET /v3/lists/pages' => ['items' => [['address' => 'news@mg.example.com', 'name' => 'News', 'members_count' => 12]]],
            'GET /v3/mg.example.com/templates' => ['items' => [['name' => 'welcome', 'description' => 'Hello']]],
        ]);
        $query = new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), [], 'key-mg', credentialData: ['domain' => 'mg.example.com']);
        $source = app(MailgunOptions::class);

        expect($source->load('mailgun.lists', $query)->toArray()['options'])->toBe([['value' => 'news@mg.example.com', 'label' => 'News', 'description' => '12 members']])
            ->and($source->load('mailgun.templates', $query)->toArray()['options'])->toBe([['value' => 'welcome', 'label' => 'welcome', 'description' => 'Hello']]);
    });
});

/**
 * A published workflow whose first step is the Mailgun events reader switched on
 * as a trigger, with its subscription synced against a faked Mailgun.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 * @param  array<string, mixed>  $data
 */
function mailgunTrigger(array $nodeConfig = [], array $routes = [], bool $sync = true, array $data = []): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeMailgun($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'mailgun') ?? Connector::factory()->create(['key' => 'mailgun']))
        ->create(['data' => ['api_key' => 'key-mg', 'domain' => 'mg.example.com', ...$data], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => 'mailgun_list_events', 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
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

describe('mailgun triggers', function () {
    it('polls events: a baseline first, then one run for each new one', function () {
        $trigger = mailgunTrigger(['event' => 'opened'], sync: false);
        $poll = fn (array $ids) => fakeMailgun(['GET /v3/mg.example.com/events' => ['items' => array_map(fn (string $id): array => mailgunEvent($id, 'opened'), $ids), 'paging' => ['next' => '']]]);

        $poll(['ev1']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['ev3', 'ev2', 'ev1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['ev2', 'ev3']);
    });
});

describe('mailgun instant delivery', function () {
    it('adds its token URL to the event\'s webhook when none exists, and starts a poll when it arrives', function () {
        $trigger = mailgunTrigger(['event' => 'delivered'], ['GET /v3/domains/mg.example.com/webhooks/delivered' => fn () => Http::response(['message' => 'Webhook not found'], 404), 'POST /v3/domains/mg.example.com/webhooks' => ['message' => 'Webhook has been created']]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('delivered@mg.example.com')
            ->and($watch->external_id)->toBe('mg.example.com')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(mailgunForm('POST /v3/domains/mg.example.com/webhooks'))->toBe([['id', 'delivered'], ['url', url('/api/hooks/apps/mailgun').'?token='.$watch->secret]]);

        $body = ['signature' => ['timestamp' => '1', 'token' => 't', 'signature' => 's'], 'event-data' => ['event' => 'delivered', 'id' => 'evt-1', 'recipient' => 'ada@example.com']];
        test()->postJson('/api/hooks/apps/mailgun?token='.$watch->secret, $body)->assertOk();
        test()->postJson('/api/hooks/apps/mailgun?token='.$watch->secret, $body)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('keeps the URLs the account already has, and refuses when the event is full', function () {
        mailgunTrigger(['event' => 'opened'], ['GET /v3/domains/mg.example.com/webhooks/opened' => ['webhook' => ['urls' => ['https://crm.example.com/hook']]], 'PUT /v3/domains/mg.example.com/webhooks/opened' => ['message' => 'ok']]);
        $watch = ProviderWatch::query()->sole();

        expect(array_column(mailgunForm('PUT /v3/domains/mg.example.com/webhooks/opened'), 1))->toBe(['https://crm.example.com/hook', url('/api/hooks/apps/mailgun').'?token='.$watch->secret]);

        ProviderWatch::query()->delete();
        $trigger = mailgunTrigger(['event' => 'clicked'], ['GET /v3/domains/mg.example.com/webhooks/clicked' => ['webhook' => ['urls' => ['https://a.test', 'https://b.test', 'https://c.test']]]]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)->and($trigger->subscription->last_error)->toContain('three addresses')->and(ProviderWatch::query()->count())->toBe(0);
        Http::assertNotSent(fn (Request $request): bool => in_array($request->method(), ['PUT', 'POST', 'DELETE'], true));
    });

    it('registers both failure events for "failed", and each event for "any"', function () {
        mailgunTrigger(['event' => 'all'], ['GET /v3/domains/mg.example.com/webhooks/*' => fn () => Http::response(['message' => 'none'], 404), 'POST /v3/domains/mg.example.com/webhooks' => ['message' => 'ok']]);

        expect(ProviderWatch::query()->sole()->resource_key)->toBe('all@mg.example.com');
        Http::assertSentCount(16);
    });

    it('refuses a wrong or missing token and is closed while the switch is off', function () {
        mailgunTrigger(['event' => 'delivered'], ['GET /v3/domains/mg.example.com/webhooks/delivered' => fn () => Http::response([], 404), 'POST /v3/domains/mg.example.com/webhooks' => ['message' => 'ok']]);
        $watch = ProviderWatch::query()->sole();
        $body = ['event-data' => ['event' => 'delivered', 'id' => 'e']];

        test()->postJson('/api/hooks/apps/mailgun?token=nope', $body)->assertUnauthorized();
        test()->postJson('/api/hooks/apps/mailgun', $body)->assertUnauthorized();
        config(['triggers.push.mailgun.enabled' => false]);
        test()->postJson('/api/hooks/apps/mailgun?token='.$watch->secret, $body)->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('puts the URL back when Mailgun lost it, and removes only its own URL with the watch', function () {
        mailgunTrigger(['event' => 'delivered'], ['GET /v3/domains/mg.example.com/webhooks/delivered' => fn () => Http::response([], 404), 'POST /v3/domains/mg.example.com/webhooks' => ['message' => 'ok']]);
        $watch = ProviderWatch::query()->sole();
        $source = app(MailgunSource::class);
        $ours = url('/api/hooks/apps/mailgun').'?token='.$watch->secret;

        fakeMailgun(['GET /v3/domains/mg.example.com/webhooks/delivered' => ['webhook' => ['urls' => ['https://crm.example.com/hook']]], 'PUT /v3/domains/mg.example.com/webhooks/delivered' => ['message' => 'ok']]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        $newOurs = url('/api/hooks/apps/mailgun').'?token='.$watch->fresh()->secret;
        expect(array_column(mailgunForm('PUT /v3/domains/mg.example.com/webhooks/delivered'), 1))->toBe(['https://crm.example.com/hook', $newOurs])->and($newOurs)->not->toBe($ours);

        fakeMailgun(['GET /v3/domains/mg.example.com/webhooks/delivered' => ['webhook' => ['urls' => ['https://crm.example.com/hook', $newOurs]]], 'PUT /v3/domains/mg.example.com/webhooks/delivered' => ['message' => 'ok']]);
        app(WatchManager::class)->remove($source, $watch->fresh());

        expect(ProviderWatch::query()->count())->toBe(0)->and(array_column(mailgunForm('PUT /v3/domains/mg.example.com/webhooks/delivered'), 1))->toBe(['https://crm.example.com/hook']);
    });

    it('deletes the event\'s webhook when ours was the only URL', function () {
        mailgunTrigger(['event' => 'delivered'], ['GET /v3/domains/mg.example.com/webhooks/delivered' => fn () => Http::response([], 404), 'POST /v3/domains/mg.example.com/webhooks' => ['message' => 'ok']]);
        $watch = ProviderWatch::query()->sole();
        $ours = url('/api/hooks/apps/mailgun').'?token='.$watch->secret;

        fakeMailgun(['GET /v3/domains/mg.example.com/webhooks/delivered' => ['webhook' => ['urls' => [$ours]]], 'DELETE /v3/domains/mg.example.com/webhooks/delivered' => ['message' => 'removed']]);
        app(WatchManager::class)->remove(app(MailgunSource::class), $watch->fresh());

        expect(ProviderWatch::query()->count())->toBe(0);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE');
    });
});
