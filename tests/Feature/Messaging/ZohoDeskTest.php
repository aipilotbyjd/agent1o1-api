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
use App\Nodes\Integrations\ZohoDesk\AbstractZohoDeskNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskAddCommentNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskCreateTicketNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskDeleteTicketNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskFindContactNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskGetTicketNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskListChangedTicketsNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskListCustomerRepliesNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskListTicketsNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskSendReplyNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskUpdateTicketNode;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Triggers\Push\ZohoDeskSource;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ZohoDeskOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const ZDESK_TICKET = '4876876000001234567';

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function zdeskData(array $data = []): array
{
    return ['region' => 'us', 'client_id' => 'cid', 'api_key' => 'csecret', 'refresh_token' => 'rtoken', 'org_id' => '6001', ...$data];
}

/**
 * A run with a connected Zoho Desk account in its workspace.
 *
 * @param  array<string, mixed>  $data
 */
function zdeskRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'zoho_desk') ?? Connector::factory()->create(['key' => 'zoho_desk']))->id,
        'data' => zdeskData($data),
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Zoho Desk and the Zoho login. A key is "METHOD /path" under /api/v1; the
 * value is the JSON body (null is a 204), or a closure returning a response. A
 * route nobody answers is a 404, so a node that calls something unexpected fails.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeZohoDesk(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['*zoho*' => function (Request $request) use ($routes) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/oauth/v2/token') {
            return Http::response(['access_token' => '1000.zoho-session', 'api_domain' => 'https://www.zohoapis.com', 'token_type' => 'Bearer', 'expires_in' => 3600]);
        }

        $key = $request->method().' '.preg_replace('~^/api/v1~', '', $path);
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['errorCode' => 'URL_NOT_FOUND', 'message' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

function zdeskRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/api/v1~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function zdeskBody(string $route): array
{
    return (array) json_decode(zdeskRequest($route)->body(), true);
}

/**
 * @return array<string, string>
 */
function zdeskQuery(string $route): array
{
    parse_str((string) parse_url(zdeskRequest($route)->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function zdeskTicket(string $id = ZDESK_TICKET, array $extra = []): array
{
    return ['id' => $id, 'ticketNumber' => '101', 'subject' => 'Cannot log in', 'status' => 'Open', 'priority' => 'High', 'channel' => 'Email', 'email' => 'ada@example.com', 'departmentId' => '7001', 'createdTime' => now()->subMinutes(10)->utc()->format('Y-m-d\TH:i:s.v\Z'), 'modifiedTime' => now()->subMinutes(5)->utc()->format('Y-m-d\TH:i:s.v\Z'), 'customerResponseTime' => now()->subMinutes(5)->utc()->format('Y-m-d\TH:i:s.v\Z'), ...$extra];
}

describe('zoho desk nodes', function () {
    it('registers the nodes under the zoho_desk connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'zoho_desk_'));

        expect(count($types))->toBeGreaterThanOrEqual(20);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractZohoDeskNode::class)->and($registry->resolve($type)->category())->toBe('zoho_desk');
        }
    });

    it('creates a ticket at the data center with the token and organization in headers', function () {
        fakeZohoDesk(['POST /tickets' => zdeskTicket()]);

        $output = (new ZohoDeskCreateTicketNode)->execute(zdeskRun(), ['department_id' => '7001', 'subject' => ' Cannot log in ', 'email' => 'Ada@Example.com', 'priority' => 'High', 'custom_fields' => '{"cf_plan": "Gold"}'], []);

        $request = zdeskRequest('POST /tickets');

        expect($output['id'])->toBe(ZDESK_TICKET)
            ->and($request->url())->toStartWith('https://desk.zoho.com/api/v1/tickets')
            ->and($request->header('Authorization')[0])->toBe('Zoho-oauthtoken 1000.zoho-session')
            ->and($request->header('orgId')[0])->toBe('6001')
            ->and(zdeskBody('POST /tickets'))->toMatchArray(['departmentId' => '7001', 'subject' => 'Cannot log in', 'priority' => 'High', 'channel' => 'Web', 'cf' => ['cf_plan' => 'Gold']]);
    });

    it('refuses a connection without the organization id, and a bad ticket id', function () {
        fakeZohoDesk([]);

        expect(fn () => (new ZohoDeskGetTicketNode)->execute(zdeskRun(['org_id' => '']), ['ticket_id' => ZDESK_TICKET], []))->toThrow(InvalidArgumentException::class, 'organization id')
            ->and(fn () => (new ZohoDeskGetTicketNode)->execute(zdeskRun(), ['ticket_id' => '../x'], []))->toThrow(InvalidArgumentException::class);
    });

    it('says a missing ticket is not found, and a found one is', function () {
        fakeZohoDesk(['GET /tickets/'.ZDESK_TICKET => fn () => Http::response(['errorCode' => 'INVALID_DATA', 'message' => 'x'], 404)]);
        $missing = (new ZohoDeskGetTicketNode)->execute(zdeskRun(), ['ticket_id' => ZDESK_TICKET], []);

        fakeZohoDesk(['GET /tickets/'.ZDESK_TICKET => zdeskTicket(extra: ['contact' => ['firstName' => 'Ada', 'lastName' => 'Lovelace']])]);
        $found = (new ZohoDeskGetTicketNode)->execute(zdeskRun(), ['ticket_id' => ZDESK_TICKET], []);

        expect($missing)->toBe(['found' => false, 'id' => ZDESK_TICKET])->and($found)->toMatchArray(['found' => true, 'contact' => 'Ada Lovelace', 'subject' => 'Cannot log in']);
    });

    it('lists tickets across pages and treats an empty answer as none', function () {
        $page = 0;
        fakeZohoDesk(['GET /tickets' => function () use (&$page) {
            $page++;

            return $page === 1 ? ['data' => array_map(fn (int $n): array => zdeskTicket((string) (4876876000000000000 + $n)), range(1, 100))] : ['data' => [zdeskTicket('4876876000000009999')]];
        }]);

        $output = (new ZohoDeskListTicketsNode)->execute(zdeskRun(), ['limit' => 101, 'status' => 'Open'], []);

        expect($output['count'])->toBe(101)->and($page)->toBe(2);

        fakeZohoDesk(['GET /tickets' => null]);
        expect((new ZohoDeskListTicketsNode)->execute(zdeskRun(), [], [])['count'])->toBe(0);
    });

    it('updates only what is filled in, and needs something to change', function () {
        fakeZohoDesk(['PATCH /tickets/'.ZDESK_TICKET => zdeskTicket(extra: ['status' => 'On Hold'])]);

        (new ZohoDeskUpdateTicketNode)->execute(zdeskRun(), ['ticket_id' => ZDESK_TICKET, 'status' => 'On Hold'], []);

        expect(zdeskBody('PATCH /tickets/'.ZDESK_TICKET))->toBe(['status' => 'On Hold'])
            ->and(fn () => (new ZohoDeskUpdateTicketNode)->execute(zdeskRun(), ['ticket_id' => ZDESK_TICKET], []))->toThrow(InvalidArgumentException::class);
    });

    it('moves a ticket to the trash by id list', function () {
        fakeZohoDesk(['POST /tickets/moveToTrash' => []]);

        $output = (new ZohoDeskDeleteTicketNode)->execute(zdeskRun(), ['ticket_id' => ZDESK_TICKET], []);

        expect($output)->toBe(['trashed' => true, 'id' => ZDESK_TICKET])->and(zdeskBody('POST /tickets/moveToTrash'))->toBe(['ticketIds' => [ZDESK_TICKET]]);
    });

    it('adds a private comment by default and a public one on request', function () {
        fakeZohoDesk(['POST /tickets/'.ZDESK_TICKET.'/comments' => ['id' => '55', 'isPublic' => false]]);

        $output = (new ZohoDeskAddCommentNode)->execute(zdeskRun(), ['ticket_id' => ZDESK_TICKET, 'content' => 'Checked the logs'], []);

        expect($output['public'])->toBeFalse()->and(zdeskBody('POST /tickets/'.ZDESK_TICKET.'/comments'))->toMatchArray(['content' => 'Checked the logs', 'isPublic' => false]);
    });

    it('replies by email and closes the ticket when asked', function () {
        fakeZohoDesk(['POST /tickets/'.ZDESK_TICKET.'/sendReply' => ['id' => '77'], 'PATCH /tickets/'.ZDESK_TICKET => zdeskTicket(extra: ['status' => 'Closed'])]);

        $output = (new ZohoDeskSendReplyNode)->execute(zdeskRun(), ['ticket_id' => ZDESK_TICKET, 'to' => 'ada@example.com', 'from_email' => 'support@acme.com', 'content' => 'Fixed.', 'close' => true], []);

        expect($output)->toMatchArray(['sent' => true, 'thread_id' => '77', 'closed' => true])
            ->and(zdeskBody('POST /tickets/'.ZDESK_TICKET.'/sendReply'))->toMatchArray(['channel' => 'EMAIL', 'to' => 'ada@example.com', 'fromEmailAddress' => 'support@acme.com', 'contentType' => 'plainText'])
            ->and(zdeskBody('PATCH /tickets/'.ZDESK_TICKET))->toBe(['status' => 'Closed']);
    });

    it('finds a contact by email, or says there is none', function () {
        fakeZohoDesk(['GET /contacts/search' => ['data' => [['id' => '9', 'lastName' => 'Lovelace', 'email' => 'ada@example.com']]]]);
        $found = (new ZohoDeskFindContactNode)->execute(zdeskRun(), ['email' => 'ada@example.com'], []);

        fakeZohoDesk(['GET /contacts/search' => null]);
        $none = (new ZohoDeskFindContactNode)->execute(zdeskRun(), ['email' => 'nobody@example.com'], []);

        expect($found)->toMatchArray(['found' => true, 'id' => '9'])->and($none)->toBe(['found' => false]);
    });

    it('explains a Desk error with a hint', function () {
        fakeZohoDesk(['GET /tickets/'.ZDESK_TICKET => fn () => Http::response(['errorCode' => 'OAUTH_SCOPE_MISMATCH', 'message' => 'The scope is not allowed'], 401), 'GET /tickets' => fn () => Http::response(['message' => 'Slow down'], 429)]);

        expect(fn () => (new ZohoDeskGetTicketNode)->execute(zdeskRun(), ['ticket_id' => ZDESK_TICKET], []))->toThrow(RuntimeException::class, 'Reconnect Zoho Desk')
            ->and(fn () => (new ZohoDeskListTicketsNode)->execute(zdeskRun(), [], []))->toThrow(RuntimeException::class, 'rate limiting');
    });
});

describe('zoho desk options', function () {
    it('lists departments and active agents', function () {
        fakeZohoDesk(['GET /departments' => ['data' => [['id' => '7001', 'name' => 'Support'], ['id' => '7002', 'name' => 'Billing']]], 'GET /agents' => ['data' => [['id' => '5', 'firstName' => 'Ada', 'lastName' => 'Lovelace', 'emailId' => 'ada@acme.com']]]]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = new NodeOptionsQuery($workspace, User::factory()->create(), [], 'csecret', credentialData: zdeskData());
        $source = app(ZohoDeskOptions::class);

        expect($source->load('zoho_desk.departments', $query)->toArray()['options'])->toBe([['value' => '7001', 'label' => 'Support'], ['value' => '7002', 'label' => 'Billing']])
            ->and($source->load('zoho_desk.agents', $query)->toArray()['options'])->toBe([['value' => '5', 'label' => 'Ada Lovelace', 'description' => 'ada@acme.com']]);
    });
});

/**
 * A published workflow whose first step is a Desk ticket reader switched on as a
 * trigger, with its subscription synced against a faked Desk.
 *
 * @param  array<string, mixed>  $routes
 */
function zdeskTrigger(string $nodeType = 'zoho_desk_list_new_tickets', array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeZohoDesk($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'zoho_desk') ?? Connector::factory()->create(['key' => 'zoho_desk']))
        ->create(['data' => zdeskData(), 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true]],
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

describe('zoho desk triggers', function () {
    it('polls new tickets: a baseline first, then one run for each', function () {
        $trigger = zdeskTrigger(sync: false);
        $poll = fn (array $ids) => fakeZohoDesk(['GET /tickets' => ['data' => array_map(fn (string $id): array => zdeskTicket($id), $ids)]]);

        $poll(['4876876000000000001']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['4876876000000000003', '4876876000000000002', '4876876000000000001']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['4876876000000000002', '4876876000000000003']);
    });

    it('sorts changed tickets by change time, drops the old ones, and makes every change its own event', function () {
        fakeZohoDesk(['GET /tickets' => ['data' => [zdeskTicket('4876876000000000001'), zdeskTicket('4876876000000000002', ['modifiedTime' => now()->subDays(5)->utc()->format('Y-m-d\TH:i:s.v\Z')])]]]);
        $node = new ZohoDeskListChangedTicketsNode;

        $output = $node->execute(zdeskRun(), ['changed_within_minutes' => 60], []);

        expect(array_column($output['tickets'], 'id'))->toBe(['4876876000000000001'])
            ->and(zdeskQuery('GET /tickets'))->toMatchArray(['sortBy' => '-modifiedTime'])
            ->and($node->triggerItemId(['id' => '1', 'updated_at' => 'a']))->not->toBe($node->triggerItemId(['id' => '1', 'updated_at' => 'b']));
    });

    it('keeps only tickets the customer answered lately', function () {
        fakeZohoDesk(['GET /tickets' => ['data' => [zdeskTicket('4876876000000000001'), zdeskTicket('4876876000000000002', ['customerResponseTime' => null])]]]);
        $node = new ZohoDeskListCustomerRepliesNode;

        $output = $node->execute(zdeskRun(), ['changed_within_minutes' => 60], []);

        expect(array_column($output['tickets'], 'id'))->toBe(['4876876000000000001'])
            ->and($node->triggerItemId(['id' => '1', 'customer_replied_at' => 'a']))->not->toBe($node->triggerItemId(['id' => '1', 'customer_replied_at' => 'b']));
    });
});

describe('zoho desk instant delivery', function () {
    it('creates a webhook for the event with a token URL, and starts a poll when it arrives', function () {
        $trigger = zdeskTrigger(routes: ['POST /webhooks' => ['id' => '88']]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('Ticket_Add')
            ->and($watch->external_id)->toBe('88')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(zdeskBody('POST /webhooks')['url'])->toBe(url('/api/hooks/apps/zoho_desk').'?token='.$watch->secret)
            ->and(zdeskBody('POST /webhooks')['subscriptions'])->toHaveKey('Ticket_Add');

        $body = [['eventType' => 'Ticket_Add', 'payload' => ['id' => ZDESK_TICKET]]];
        test()->postJson('/api/hooks/apps/zoho_desk?token='.$watch->secret, $body)->assertOk();
        test()->postJson('/api/hooks/apps/zoho_desk?token='.$watch->secret, $body)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('listens for the thread event on a customer-replied trigger', function () {
        zdeskTrigger('zoho_desk_list_customer_replies', ['POST /webhooks' => ['id' => '89']]);

        expect(ProviderWatch::query()->sole()->resource_key)->toBe('Ticket_Thread_Add');
    });

    it('refuses a wrong or missing token and is closed while the switch is off', function () {
        zdeskTrigger(routes: ['POST /webhooks' => ['id' => '88']]);
        $watch = ProviderWatch::query()->sole();
        $body = [['eventType' => 'Ticket_Add', 'payload' => ['id' => ZDESK_TICKET]]];

        test()->postJson('/api/hooks/apps/zoho_desk?token=nope', $body)->assertUnauthorized();
        test()->postJson('/api/hooks/apps/zoho_desk', $body)->assertUnauthorized();
        config(['triggers.push.zoho_desk.enabled' => false]);
        test()->postJson('/api/hooks/apps/zoho_desk?token='.$watch->secret, $body)->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('says so when Desk refuses the webhook, and drops the watch', function () {
        $trigger = zdeskTrigger(routes: ['POST /webhooks' => fn () => Http::response(['errorCode' => 'FORBIDDEN', 'message' => 'Admins only'], 403)]);

        expect($trigger->subscription->status)->not->toBe(SubscriptionStatus::Active)->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('makes the webhook again when Desk lost it, and removes it with the watch', function () {
        zdeskTrigger(routes: ['POST /webhooks' => ['id' => '88']]);
        $watch = ProviderWatch::query()->sole();
        $source = app(ZohoDeskSource::class);

        fakeZohoDesk(['GET /webhooks/88' => fn () => Http::response(['message' => 'gone'], 404), 'POST /webhooks' => ['id' => '90'], 'DELETE /webhooks/88' => []]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('90');

        fakeZohoDesk(['GET /webhooks/90' => ['id' => '90', 'isEnabled' => true]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/webhooks'));

        fakeZohoDesk(['DELETE /webhooks/90' => []]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
