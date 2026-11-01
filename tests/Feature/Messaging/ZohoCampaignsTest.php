<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\ZohoCampaigns\AbstractZohoCampaignsNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsAddSubscriberNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsListCampaignsNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsListListsNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsListSubscribersNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsSendCampaignNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsUnsubscribeNode;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ZohoCampaignsOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const ZCAMP_LIST = 'abc123def456ghi789';

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function zcampData(array $data = []): array
{
    return ['region' => 'us', 'client_id' => 'cid', 'api_key' => 'csecret', 'refresh_token' => 'rtoken', ...$data];
}

function zcampRun(): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'zoho_campaigns') ?? Connector::factory()->create(['key' => 'zoho_campaigns']))->id,
        'data' => zcampData(),
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Zoho Campaigns and the Zoho login. A key is "METHOD /path" under /api/v1.1.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeZohoCampaigns(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['*zoho*' => function (Request $request) use ($routes) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/oauth/v2/token') {
            return Http::response(['access_token' => '1000.zoho-session', 'api_domain' => 'https://www.zohoapis.com', 'token_type' => 'Bearer', 'expires_in' => 3600]);
        }

        $key = $request->method().' '.preg_replace('~^/api/v1\.1~', '', $path);
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['status' => 'error', 'message' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function zcampRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/api/v1\.1~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * The form fields of a post, or the query of a get.
 *
 * @return array<string, mixed>
 */
function zcampParams(string $route): array
{
    $request = zcampRequest($route);
    parse_str($request->method() === 'GET' ? (string) parse_url($request->url(), PHP_URL_QUERY) : $request->body(), $params);

    return $params;
}

describe('zoho campaigns nodes', function () {
    it('registers the nodes under the zoho_campaigns connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'zoho_campaigns_'));

        expect(count($types))->toBeGreaterThanOrEqual(8);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractZohoCampaignsNode::class)->and($registry->resolve($type)->category())->toBe('zoho_campaigns');
        }
    });

    it('lists mailing lists asking for JSON with the token in a header', function () {
        fakeZohoCampaigns(['GET /getmailinglists' => ['status' => 'success', 'list_of_details' => [['listkey' => ZCAMP_LIST, 'listname' => 'Newsletter', 'noofcontacts' => '120']]]]);

        $output = (new ZohoCampaignsListListsNode)->execute(zcampRun(), [], []);

        $request = zcampRequest('GET /getmailinglists');

        expect($output['lists'])->toBe([['key' => ZCAMP_LIST, 'name' => 'Newsletter', 'subscribers' => 120, 'created_at' => null]])
            ->and($request->url())->toStartWith('https://campaigns.zoho.com/api/v1.1/getmailinglists')
            ->and($request->header('Authorization')[0])->toBe('Zoho-oauthtoken 1000.zoho-session')
            ->and(zcampParams('GET /getmailinglists'))->toMatchArray(['resfmt' => 'JSON', 'fromindex' => '1']);
    });

    it('subscribes a person with the contact info as JSON in a form post', function () {
        fakeZohoCampaigns(['POST /json/listsubscribe' => ['status' => 'success', 'message' => 'Contact added successfully']]);

        $output = (new ZohoCampaignsAddSubscriberNode)->execute(zcampRun(), ['list_key' => ZCAMP_LIST, 'email' => 'Ada@Example.com', 'first_name' => 'Ada', 'extra' => '{"Company Name": "Acme"}'], []);

        $params = zcampParams('POST /json/listsubscribe');

        expect($output)->toBe(['subscribed' => true, 'email' => 'ada@example.com', 'list_key' => ZCAMP_LIST])
            ->and($params['listkey'])->toBe(ZCAMP_LIST)
            ->and(json_decode($params['contactinfo'], true))->toBe(['Company Name' => 'Acme', 'Contact Email' => 'ada@example.com', 'First Name' => 'Ada']);
    });

    it('unsubscribes a person and refuses a bad list key or address', function () {
        fakeZohoCampaigns(['POST /json/listunsubscribe' => ['status' => 'success']]);

        $output = (new ZohoCampaignsUnsubscribeNode)->execute(zcampRun(), ['list_key' => ZCAMP_LIST, 'email' => 'ada@example.com'], []);

        expect($output['unsubscribed'])->toBeTrue()
            ->and(fn () => (new ZohoCampaignsUnsubscribeNode)->execute(zcampRun(), ['list_key' => 'a&b=c', 'email' => 'ada@example.com'], []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new ZohoCampaignsUnsubscribeNode)->execute(zcampRun(), ['list_key' => ZCAMP_LIST, 'email' => 'nope'], []))->toThrow(InvalidArgumentException::class);
    });

    it('treats a 200 answer that says error as an error', function () {
        fakeZohoCampaigns(['POST /sendcampaign' => ['status' => 'error', 'code' => '2401', 'message' => 'Campaign is not ready']]);

        expect(fn () => (new ZohoCampaignsSendCampaignNode)->execute(zcampRun(), ['campaign_key' => ZCAMP_LIST], []))->toThrow(RuntimeException::class, 'Campaign is not ready');
    });

    it('lists subscribers newest first and campaigns by status', function () {
        fakeZohoCampaigns(['GET /getlistsubscribers' => ['status' => 'success', 'list_of_details' => [['contact_email' => 'Ada@Example.com', 'firstname' => 'Ada']]], 'GET /recentcampaigns' => ['recent_campaigns' => [['campaign_key' => 'k1', 'campaign_name' => 'March', 'campaign_status' => 'Sent'], ['campaign_key' => 'k2', 'campaign_name' => 'April', 'campaign_status' => 'Draft']]]]);

        $subscribers = (new ZohoCampaignsListSubscribersNode)->execute(zcampRun(), ['list_key' => ZCAMP_LIST], []);
        $campaigns = (new ZohoCampaignsListCampaignsNode)->execute(zcampRun(), ['status' => 'sent'], []);

        expect($subscribers['subscribers'][0])->toMatchArray(['id' => ZCAMP_LIST.':ada@example.com', 'email' => 'ada@example.com', 'first_name' => 'Ada'])
            ->and(zcampParams('GET /getlistsubscribers'))->toMatchArray(['listkey' => ZCAMP_LIST, 'status' => 'active', 'sort' => 'desc'])
            ->and(array_column($campaigns['campaigns'], 'id'))->toBe(['k1']);
    });
});

describe('zoho campaigns options', function () {
    it('lists the mailing lists, filtered by the search', function () {
        fakeZohoCampaigns(['GET /getmailinglists' => ['list_of_details' => [['listkey' => ZCAMP_LIST, 'listname' => 'Newsletter', 'noofcontacts' => '5'], ['listkey' => 'zzz999yyy888xxx', 'listname' => 'Beta testers']]]]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

        $page = app(ZohoCampaignsOptions::class)->load('zoho_campaigns.lists', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'csecret', 'news', credentialData: zcampData()));

        expect($page->toArray()['options'])->toBe([['value' => ZCAMP_LIST, 'label' => 'Newsletter', 'description' => '5 subscribers']]);
    });
});

describe('zoho campaigns triggers', function () {
    it('polls new subscribers: a baseline first, then one run for each', function () {
        Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
        fakeZohoCampaigns([]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'zoho_campaigns') ?? Connector::factory()->create(['key' => 'zoho_campaigns']))
            ->create(['data' => zcampData(), 'created_by' => $owner->id]);
        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'zoho_campaigns_list_new_subscribers', 'config' => ['credential_id' => $credential->id, 'list_key' => ZCAMP_LIST, 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);
        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        SyncTriggerSubscription::dispatchSync($trigger->id);
        $poll = fn (array $emails) => fakeZohoCampaigns(['GET /getlistsubscribers' => ['list_of_details' => array_map(fn (string $email): array => ['contact_email' => $email], $emails)]]);

        $poll(['a@x.com']);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['c@x.com', 'b@x.com', 'a@x.com']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.email')->sort()->values()->all())->toBe(['b@x.com', 'c@x.com']);
    });
});
