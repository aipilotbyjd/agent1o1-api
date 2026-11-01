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
use App\Nodes\Integrations\ZohoMail\AbstractZohoMailNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailGetMessageNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailListMessagesNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailMarkReadNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailMoveMessageNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailSearchMessagesNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailSendEmailNode;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ZohoMailOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const ZMAIL_ACCOUNT = '8000001';
const ZMAIL_FOLDER = '9000000001';
const ZMAIL_MESSAGE = '1700000000000001';

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function zmailData(array $data = []): array
{
    return ['region' => 'us', 'client_id' => 'cid', 'api_key' => 'csecret', 'refresh_token' => 'rtoken', ...$data];
}

/**
 * @param  array<string, mixed>  $data
 */
function zmailRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'zoho_mail') ?? Connector::factory()->create(['key' => 'zoho_mail']))->id,
        'data' => zmailData($data),
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Zoho Mail and the Zoho login. A key is "METHOD /path" under /api; the
 * account is found by GET /accounts unless the connection names it.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeZohoMail(array $routes): void
{
    $routes = ['GET /accounts' => ['data' => [['accountId' => ZMAIL_ACCOUNT, 'primaryEmailAddress' => 'me@acme.com']]], ...$routes];

    Http::swap(new Factory);
    Http::fake(['*zoho*' => function (Request $request) use ($routes) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/oauth/v2/token') {
            return Http::response(['access_token' => '1000.zoho-session', 'api_domain' => 'https://www.zohoapis.com', 'token_type' => 'Bearer', 'expires_in' => 3600]);
        }

        $key = $request->method().' '.preg_replace('~^/api~', '', $path);
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['status' => ['code' => 404], 'data' => ['errorCode' => "Nothing fakes {$key}"]], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function zmailRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/api~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function zmailBody(string $route): array
{
    return (array) json_decode(zmailRequest($route)->body(), true);
}

/**
 * @return array<string, string>
 */
function zmailQuery(string $route): array
{
    parse_str((string) parse_url(zmailRequest($route)->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * @return array<string, mixed>
 */
function zmailMessage(string $id = ZMAIL_MESSAGE): array
{
    return ['messageId' => $id, 'folderId' => ZMAIL_FOLDER, 'subject' => 'Hello', 'sender' => 'Ada', 'fromAddress' => 'ada@example.com', 'toAddress' => 'me@acme.com', 'summary' => 'Hi there', 'status' => '0', 'hasAttachment' => '0', 'receivedTime' => '1772000000000'];
}

describe('zoho mail nodes', function () {
    it('registers the nodes under the zoho_mail connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'zoho_mail_'));

        expect(count($types))->toBeGreaterThanOrEqual(9);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractZohoMailNode::class)->and($registry->resolve($type)->category())->toBe('zoho_mail');
        }
    });

    it('lists messages of a folder in the first account, newest first, with the token in a header', function () {
        fakeZohoMail(['GET /accounts/'.ZMAIL_ACCOUNT.'/messages/view' => ['data' => [zmailMessage()]]]);

        $output = (new ZohoMailListMessagesNode)->execute(zmailRun(), ['folder_id' => ZMAIL_FOLDER, 'unread_only' => true], []);

        $request = zmailRequest('GET /accounts/'.ZMAIL_ACCOUNT.'/messages/view');

        expect($output['messages'][0])->toMatchArray(['id' => ZMAIL_MESSAGE, 'subject' => 'Hello', 'from' => 'ada@example.com', 'unread' => true, 'received_at' => gmdate('c', 1772000000)])
            ->and($request->url())->toStartWith('https://mail.zoho.com/api/accounts/')
            ->and($request->header('Authorization')[0])->toBe('Zoho-oauthtoken 1000.zoho-session')
            ->and(zmailQuery('GET /accounts/'.ZMAIL_ACCOUNT.'/messages/view'))->toMatchArray(['folderId' => ZMAIL_FOLDER, 'status' => 'unread', 'start' => '1']);
    });

    it('uses the account id of the connection without asking for the accounts', function () {
        fakeZohoMail(['GET /accounts/555/messages/view' => ['data' => []]]);

        (new ZohoMailListMessagesNode)->execute(zmailRun(['account_id' => '555']), [], []);

        Http::assertNotSent(fn (Request $request): bool => parse_url($request->url(), PHP_URL_PATH) === '/api/accounts');
    });

    it('searches by joining the terms and needs at least one', function () {
        fakeZohoMail(['GET /accounts/'.ZMAIL_ACCOUNT.'/messages/search' => ['data' => [zmailMessage()]]]);

        (new ZohoMailSearchMessagesNode)->execute(zmailRun(), ['from' => 'ada@example.com', 'subject' => 'Hello'], []);

        expect(zmailQuery('GET /accounts/'.ZMAIL_ACCOUNT.'/messages/search')['searchKey'])->toBe('from:ada@example.com::subject:Hello')
            ->and(fn () => (new ZohoMailSearchMessagesNode)->execute(zmailRun(), [], []))->toThrow(InvalidArgumentException::class);
    });

    it('says a missing message is not found, and returns the content of one that is', function () {
        $path = 'GET /accounts/'.ZMAIL_ACCOUNT.'/folders/'.ZMAIL_FOLDER.'/messages/'.ZMAIL_MESSAGE.'/content';
        fakeZohoMail([$path => fn () => Http::response(['data' => ['errorCode' => 'INVALID_MESSAGE']], 404)]);
        $missing = (new ZohoMailGetMessageNode)->execute(zmailRun(), ['folder_id' => ZMAIL_FOLDER, 'message_id' => ZMAIL_MESSAGE], []);

        fakeZohoMail([$path => ['data' => ['content' => '<p>Hi</p>']]]);
        $found = (new ZohoMailGetMessageNode)->execute(zmailRun(), ['folder_id' => ZMAIL_FOLDER, 'message_id' => ZMAIL_MESSAGE], []);

        expect($missing)->toBe(['found' => false, 'id' => ZMAIL_MESSAGE])->and($found['content'])->toBe('<p>Hi</p>');
    });

    it('sends from the main address, with checked recipients', function () {
        fakeZohoMail(['POST /accounts/'.ZMAIL_ACCOUNT.'/messages' => ['data' => ['messageId' => '77']]]);

        $output = (new ZohoMailSendEmailNode)->execute(zmailRun(), ['to' => 'Ada@Example.com, bob@example.com', 'subject' => 'Hi', 'content' => 'Hello', 'cc' => 'cc@example.com'], []);

        expect($output)->toMatchArray(['sent' => true, 'from' => 'me@acme.com', 'to' => ['ada@example.com', 'bob@example.com'], 'id' => '77'])
            ->and(zmailBody('POST /accounts/'.ZMAIL_ACCOUNT.'/messages'))->toMatchArray(['fromAddress' => 'me@acme.com', 'toAddress' => 'ada@example.com,bob@example.com', 'ccAddress' => 'cc@example.com', 'mailFormat' => 'plaintext'])
            ->and(fn () => (new ZohoMailSendEmailNode)->execute(zmailRun(), ['to' => 'nope', 'subject' => 'x', 'content' => 'y'], []))->toThrow(InvalidArgumentException::class);
    });

    it('marks a message read or unread and moves it', function () {
        fakeZohoMail(['PUT /accounts/'.ZMAIL_ACCOUNT.'/updatemessage' => ['data' => []]]);

        (new ZohoMailMarkReadNode)->execute(zmailRun(), ['folder_id' => ZMAIL_FOLDER, 'message_id' => ZMAIL_MESSAGE], []);
        expect(zmailBody('PUT /accounts/'.ZMAIL_ACCOUNT.'/updatemessage'))->toBe(['mode' => 'markAsRead', 'messageId' => [ZMAIL_MESSAGE], 'folderId' => ZMAIL_FOLDER]);

        fakeZohoMail(['PUT /accounts/'.ZMAIL_ACCOUNT.'/updatemessage' => ['data' => []]]);
        $moved = (new ZohoMailMoveMessageNode)->execute(zmailRun(), ['folder_id' => ZMAIL_FOLDER, 'message_id' => ZMAIL_MESSAGE, 'destination_id' => '9000000002'], []);

        expect($moved)->toBe(['moved' => true, 'id' => ZMAIL_MESSAGE, 'folder_id' => '9000000002'])
            ->and(zmailBody('PUT /accounts/'.ZMAIL_ACCOUNT.'/updatemessage'))->toMatchArray(['mode' => 'moveMessage', 'destfolderId' => '9000000002']);
    });

    it('explains an error with a hint', function () {
        fakeZohoMail(['GET /accounts/'.ZMAIL_ACCOUNT.'/folders' => fn () => Http::response(['data' => ['errorCode' => 'INVALID_OAUTHTOKEN']], 401)]);

        expect(fn () => (new App\Nodes\Integrations\ZohoMail\ZohoMailListFoldersNode)->execute(zmailRun(), [], []))->toThrow(RuntimeException::class, 'Reconnect Zoho Mail');
    });
});

describe('zoho mail options', function () {
    it('lists the folders of the first account, filtered by the search', function () {
        fakeZohoMail(['GET /accounts/'.ZMAIL_ACCOUNT.'/folders' => ['data' => [['folderId' => ZMAIL_FOLDER, 'folderName' => 'Inbox'], ['folderId' => '9000000002', 'folderName' => 'Sent']]]]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

        $page = app(ZohoMailOptions::class)->load('zoho_mail.folders', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'csecret', 'inb', credentialData: zmailData()));

        expect($page->toArray()['options'])->toBe([['value' => ZMAIL_FOLDER, 'label' => 'Inbox']]);
    });
});

describe('zoho mail trigger', function () {
    it('polls new mail: a baseline first, then one run for each new message', function () {
        Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
        fakeZohoMail([]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'zoho_mail') ?? Connector::factory()->create(['key' => 'zoho_mail']))
            ->create(['data' => zmailData(), 'created_by' => $owner->id]);
        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'zoho_mail_list_new_messages', 'config' => ['credential_id' => $credential->id, 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);
        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        SyncTriggerSubscription::dispatchSync($trigger->id);
        $poll = fn (array $ids) => fakeZohoMail(['GET /accounts/'.ZMAIL_ACCOUNT.'/messages/view' => ['data' => array_map(fn (string $id): array => zmailMessage($id), $ids)]]);

        $poll(['1700000000000001']);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['1700000000000003', '1700000000000002', '1700000000000001']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['1700000000000002', '1700000000000003']);
    });
});
