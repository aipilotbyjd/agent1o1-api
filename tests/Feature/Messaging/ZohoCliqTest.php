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
use App\Nodes\Integrations\ZohoCliq\AbstractZohoCliqNode;
use App\Nodes\Integrations\ZohoCliq\ZohoCliqListChannelsNode;
use App\Nodes\Integrations\ZohoCliq\ZohoCliqListMessagesNode;
use App\Nodes\Integrations\ZohoCliq\ZohoCliqSendChannelMessageNode;
use App\Nodes\Integrations\ZohoCliq\ZohoCliqSendChatMessageNode;
use App\Nodes\Integrations\ZohoCliq\ZohoCliqSendDirectMessageNode;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ZohoCliqOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @return array<string, mixed>
 */
function zcliqData(): array
{
    return ['region' => 'us', 'client_id' => 'cid', 'api_key' => 'csecret', 'refresh_token' => 'rtoken'];
}

function zcliqRun(): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'zoho_cliq') ?? Connector::factory()->create(['key' => 'zoho_cliq']))->id,
        'data' => zcliqData(),
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Zoho Cliq and the Zoho login. A key is "METHOD /path" under /api/v2.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeZohoCliq(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['*zoho*' => function (Request $request) use ($routes) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/oauth/v2/token') {
            return Http::response(['access_token' => '1000.zoho-session', 'api_domain' => 'https://www.zohoapis.com', 'token_type' => 'Bearer', 'expires_in' => 3600]);
        }

        $key = $request->method().' '.preg_replace('~^/api/v2~', '', rawurldecode($path));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['message' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function zcliqRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/api/v2~', '', rawurldecode((string) parse_url($pair[0]->url(), PHP_URL_PATH))) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function zcliqBody(string $route): array
{
    return (array) json_decode(zcliqRequest($route)->body(), true);
}

describe('zoho cliq nodes', function () {
    it('registers the nodes under the zoho_cliq connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'zoho_cliq_'));

        expect(count($types))->toBeGreaterThanOrEqual(6);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractZohoCliqNode::class)->and($registry->resolve($type)->category())->toBe('zoho_cliq');
        }
    });

    it('lists channels with the token in a header', function () {
        fakeZohoCliq(['GET /channels' => ['channels' => [['channel_id' => 'C1', 'name' => 'General', 'unique_name' => 'general', 'chat_id' => 'CT_1']]]]);

        $output = (new ZohoCliqListChannelsNode)->execute(zcliqRun(), [], []);

        $request = zcliqRequest('GET /channels');

        expect($output['channels'][0])->toMatchArray(['unique_name' => 'general', 'chat_id' => 'CT_1'])
            ->and($request->url())->toStartWith('https://cliq.zoho.com/api/v2/channels')
            ->and($request->header('Authorization')[0])->toBe('Zoho-oauthtoken 1000.zoho-session');
    });

    it('posts to a channel by its unique name, optionally as a bot, and refuses a bad name', function () {
        fakeZohoCliq(['POST /channelsbyname/general/message' => ['message_id' => 'M1']]);

        $output = (new ZohoCliqSendChannelMessageNode)->execute(zcliqRun(), ['channel' => 'General', 'text' => 'Deploy done', 'bot' => 'deploybot'], []);

        expect($output)->toBe(['sent' => true, 'channel' => 'general', 'id' => 'M1'])
            ->and(zcliqBody('POST /channelsbyname/general/message'))->toBe(['text' => 'Deploy done'])
            ->and(zcliqRequest('POST /channelsbyname/general/message')->url())->toContain('bot_unique_name=deploybot')
            ->and(fn () => (new ZohoCliqSendChannelMessageNode)->execute(zcliqRun(), ['channel' => '../x', 'text' => 'y'], []))->toThrow(InvalidArgumentException::class);
    });

    it('messages a person by email and a chat by id', function () {
        fakeZohoCliq(['POST /buddies/ada@acme.com/message' => ['message_id' => 'M2'], 'POST /chats/CT_9/message' => ['message_id' => 'M3']]);

        $dm = (new ZohoCliqSendDirectMessageNode)->execute(zcliqRun(), ['email' => 'Ada@Acme.com', 'text' => 'Hi'], []);
        $chat = (new ZohoCliqSendChatMessageNode)->execute(zcliqRun(), ['chat_id' => 'CT_9', 'text' => 'Hi all'], []);

        expect($dm['id'])->toBe('M2')->and($chat['id'])->toBe('M3')
            ->and(fn () => (new ZohoCliqSendDirectMessageNode)->execute(zcliqRun(), ['email' => 'nope', 'text' => 'x'], []))->toThrow(InvalidArgumentException::class);
    });

    it('reads a channel through its chat, newest first', function () {
        fakeZohoCliq(['GET /channelsbyname/general' => ['chat_id' => 'CT_1'], 'GET /chats/CT_1/messages' => ['data' => [['id' => 'a', 'time' => 1772000000000, 'text' => 'old', 'sender' => ['id' => '1', 'name' => 'Ada']], ['id' => 'b', 'time' => 1772000100000, 'text' => 'new', 'sender' => ['id' => '2', 'name' => 'Bob']]]]]);

        $output = (new ZohoCliqListMessagesNode)->execute(zcliqRun(), ['channel' => 'general'], []);

        expect(array_column($output['messages'], 'id'))->toBe(['b', 'a'])->and($output['messages'][0])->toMatchArray(['text' => 'new', 'sender' => 'Bob', 'sent_at' => gmdate('c', 1772000100)]);
    });

    it('explains an error with a hint', function () {
        fakeZohoCliq(['GET /channels' => fn () => Http::response(['message' => 'oauth_token_invalid'], 401)]);

        expect(fn () => (new ZohoCliqListChannelsNode)->execute(zcliqRun(), [], []))->toThrow(RuntimeException::class, 'Reconnect Zoho Cliq');
    });
});

describe('zoho cliq options', function () {
    it('lists channels by unique name, filtered by the search', function () {
        fakeZohoCliq(['GET /channels' => ['channels' => [['name' => 'General', 'unique_name' => 'general'], ['name' => 'Ops', 'unique_name' => 'ops', 'description' => 'On call']]]]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

        $page = app(ZohoCliqOptions::class)->load('zoho_cliq.channels', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'csecret', 'ops', credentialData: zcliqData()));

        expect($page->toArray()['options'])->toBe([['value' => 'ops', 'label' => 'Ops', 'description' => 'On call']]);
    });
});

describe('zoho cliq trigger', function () {
    it('polls messages: a baseline first, then one run for each new one', function () {
        Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
        fakeZohoCliq([]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'zoho_cliq') ?? Connector::factory()->create(['key' => 'zoho_cliq']))
            ->create(['data' => zcliqData(), 'created_by' => $owner->id]);
        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'zoho_cliq_list_messages', 'config' => ['credential_id' => $credential->id, 'chat_id' => 'CT_1', 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);
        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        SyncTriggerSubscription::dispatchSync($trigger->id);
        $poll = fn (array $ids) => fakeZohoCliq(['GET /chats/CT_1/messages' => ['data' => array_map(fn (string $id, int $n): array => ['id' => $id, 'time' => 1772000000000 + $n, 'text' => $id], $ids, array_keys($ids))]]);

        $poll(['m1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['m3', 'm2', 'm1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['m2', 'm3']);
    });
});
