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
use App\Nodes\Integrations\Zoho\AbstractZohoNode;
use App\Nodes\Integrations\Zoho\ZohoAddNoteNode;
use App\Nodes\Integrations\Zoho\ZohoCreateDealNode;
use App\Nodes\Integrations\Zoho\ZohoCreateLeadNode;
use App\Nodes\Integrations\Zoho\ZohoCreateTaskNode;
use App\Nodes\Integrations\Zoho\ZohoDeleteLeadNode;
use App\Nodes\Integrations\Zoho\ZohoGetLeadNode;
use App\Nodes\Integrations\Zoho\ZohoGetMeNode;
use App\Nodes\Integrations\Zoho\ZohoListChangedDealsNode;
use App\Nodes\Integrations\Zoho\ZohoRunCoqlNode;
use App\Nodes\Integrations\Zoho\ZohoSearchContactsNode;
use App\Nodes\Integrations\Zoho\ZohoUpdateLeadNode;
use App\Nodes\Integrations\Zoho\ZohoUpsertRecordNode;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Triggers\Push\ZohoSource;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ZohoOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const ZOHO_ID = '4876876000001234567';

/**
 * The connection fields of a Zoho account.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function zohoData(array $data = []): array
{
    return ['region' => 'us', 'client_id' => 'cid', 'api_key' => 'csecret', 'refresh_token' => 'rtoken', ...$data];
}

/**
 * A run with a connected Zoho CRM account in its workspace.
 *
 * @param  array<string, mixed>  $data
 */
function zohoRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'zoho') ?? Connector::factory()->create(['key' => 'zoho']))->id,
        'data' => zohoData($data),
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Zoho: the login at the accounts server of the data center, and the CRM API
 * at its API root. A key is "METHOD /path" under /crm/v7; the value is the JSON body
 * (null is a 204), or a closure returning a response. A route nobody answers is an
 * error, so a node that calls something unexpected fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeZoho(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['*zoho*' => function (Request $request) use ($routes) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/oauth/v2/token') {
            return Http::response(['access_token' => '1000.zoho-session', 'api_domain' => 'https://www.zohoapis.com', 'token_type' => 'Bearer', 'expires_in' => 3600]);
        }

        $key = $request->method().' '.preg_replace('~^/crm/v7~', '', $path);
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['code' => 'INVALID_URL_PATTERN', 'message' => "Nothing fakes {$key}", 'status' => 'error'], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

/**
 * The request made to a route.
 */
function zohoRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/crm/v7~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function zohoBody(string $route): array
{
    return (array) json_decode(zohoRequest($route)->body(), true);
}

/**
 * @return array<string, string>
 */
function zohoQuery(string $route): array
{
    parse_str((string) parse_url(zohoRequest($route)->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function zohoRecord(string $id = ZOHO_ID, array $extra = []): array
{
    return ['id' => $id, 'Last_Name' => 'Lovelace', 'Email' => 'ada@example.com', 'Owner' => ['name' => 'Grace Hopper', 'id' => '4876876000000111111'], 'Created_Time' => '2026-03-01T10:00:00+00:00', 'Modified_Time' => '2026-03-01T11:00:00+00:00', ...$extra];
}

/**
 * A successful single-record write.
 *
 * @return array<string, mixed>
 */
function zohoWritten(string $id = ZOHO_ID, string $action = 'insert'): array
{
    return ['data' => [['code' => 'SUCCESS', 'details' => ['id' => $id, 'Created_Time' => '2026-03-01T10:00:00+00:00'], 'message' => 'record added', 'status' => 'success', 'action' => $action]]];
}

describe('zoho nodes', function () {
    it('registers the nodes under the zoho connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'zoho_'));

        expect(count($types))->toBeGreaterThanOrEqual(44);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractZohoNode::class)->and($registry->resolve($type)->category())->toBe('zoho');
        }
    });

    it('trades the refresh token for an access token once, and uses it at the data center\'s API root', function () {
        fakeZoho(['GET /users' => ['users' => [['id' => '1', 'full_name' => 'Ada', 'email' => 'ada@acme.com', 'role' => ['name' => 'CEO'], 'profile' => ['name' => 'Administrator']]]]]);
        $run = zohoRun(['region' => 'eu']);

        $first = (new ZohoGetMeNode)->execute($run, [], []);
        (new ZohoGetMeNode)->execute($run, [], []);

        expect($first)->toBe(['id' => '1', 'name' => 'Ada', 'email' => 'ada@acme.com', 'role' => 'CEO', 'profile' => 'Administrator']);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://accounts.zoho.eu/oauth/v2/token' && $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'rtoken' && $request['client_id'] === 'cid' && $request['client_secret'] === 'csecret');
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://www.zohoapis.eu/crm/v7/users') && $request->hasHeader('Authorization', 'Zoho-oauthtoken 1000.zoho-session'));
    });

    it('refuses an unknown data center, missing fields, and a login Zoho refuses', function () {
        fakeZoho([]);

        expect(fn () => (new ZohoGetMeNode)->execute(zohoRun(['region' => 'mars']), [], []))->toThrow(InvalidArgumentException::class, 'region must be one of')
            ->and(fn () => (new ZohoGetMeNode)->execute(zohoRun(['refresh_token' => '']), [], []))->toThrow(InvalidArgumentException::class, 'refresh token');

        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['error' => 'invalid_code'], 200)]);

        expect(fn () => (new ZohoGetMeNode)->execute(zohoRun(['refresh_token' => 'bad']), [], []))->toThrow(RuntimeException::class, 'Zoho refused the login: invalid_code');
    });

    it('creates a lead with lookups as ids, and reads the new id from the reply', function () {
        fakeZoho(['POST /Leads' => zohoWritten()]);

        $output = (new ZohoCreateLeadNode)->execute(zohoRun(), ['last_name' => 'Lovelace', 'company' => 'Acme', 'email' => 'ada@example.com', 'title' => 'CTO', 'owner_id' => '4876876000000111111', 'fields' => '{"Industry": "Retail"}'], []);

        expect($output)->toBe(['id' => ZOHO_ID, 'module' => 'Leads', 'created_at' => '2026-03-01T10:00:00+00:00'])
            ->and(zohoBody('POST /Leads'))->toBe(['data' => [['Industry' => 'Retail', 'Last_Name' => 'Lovelace', 'Company' => 'Acme', 'Email' => 'ada@example.com', 'Designation' => 'CTO', 'Owner' => ['id' => '4876876000000111111']]]]);
    });

    it('creates a deal with a number for the amount and a task linked to a record', function () {
        fakeZoho(['POST /Deals' => zohoWritten(), 'POST /Tasks' => zohoWritten('4876876000009999999')]);
        $run = zohoRun();

        (new ZohoCreateDealNode)->execute($run, ['name' => 'Acme annual plan', 'stage' => 'Qualification', 'close_date' => '2026-03-31', 'amount' => '4800', 'account_id' => '4876876000000222222'], []);
        (new ZohoCreateTaskNode)->execute($run, ['subject' => 'Call Ada', 'what_id' => ZOHO_ID, 'what_module' => 'Deals', 'due_date' => '2026-03-05'], []);

        expect(zohoBody('POST /Deals')['data'][0])->toBe(['Deal_Name' => 'Acme annual plan', 'Stage' => 'Qualification', 'Closing_Date' => '2026-03-31', 'Amount' => 4800, 'Account_Name' => ['id' => '4876876000000222222']])
            ->and(zohoBody('POST /Tasks')['data'][0])->toBe(['Subject' => 'Call Ada', 'Due_Date' => '2026-03-05', 'What_Id' => ['id' => ZOHO_ID], '$se_module' => 'Deals']);
    });

    it('checks module, field and id names, and lookups, before calling Zoho', function () {
        fakeZoho([]);
        $run = zohoRun();

        expect(fn () => (new ZohoCreateLeadNode)->execute($run, ['last_name' => 'x', 'fields' => '{"A b": 1}'], []))->toThrow(InvalidArgumentException::class, 'must be an API name')
            ->and(fn () => (new ZohoCreateLeadNode)->execute($run, ['last_name' => 'x', 'owner_id' => 'me'], []))->toThrow(InvalidArgumentException::class, 'must be a Zoho id')
            ->and(fn () => (new ZohoUpdateLeadNode)->execute($run, ['record_id' => '12/../x', 'last_name' => 'x'], []))->toThrow(InvalidArgumentException::class, 'not a Zoho id')
            ->and(fn () => (new ZohoUpdateLeadNode)->execute($run, ['record_id' => ZOHO_ID], []))->toThrow(InvalidArgumentException::class, 'Change at least one field')
            ->and(fn () => (new ZohoRunCoqlNode)->execute($run, ['query' => 'delete from Leads'], []))->toThrow(InvalidArgumentException::class, 'must start with select');
        Http::assertNothingSent();
    });

    it('says what Zoho said about a record it refuses, even though it answers 200', function () {
        fakeZoho(['POST /Leads' => ['data' => [['code' => 'MANDATORY_NOT_FOUND', 'details' => ['api_name' => 'Last_Name'], 'message' => 'required field not found', 'status' => 'error']]]]);

        expect(fn () => (new ZohoCreateLeadNode)->execute(zohoRun(), ['last_name' => 'x'], []))->toThrow(RuntimeException::class, 'Zoho error: required field not found [MANDATORY_NOT_FOUND]');
    });

    it('updates a lead by PUT and deletes it by ids', function () {
        fakeZoho(['PUT /Leads/'.ZOHO_ID => zohoWritten(action: 'update'), 'DELETE /Leads' => ['data' => [['code' => 'SUCCESS', 'status' => 'success', 'details' => ['id' => ZOHO_ID]]]]]);
        $run = zohoRun();

        $updated = (new ZohoUpdateLeadNode)->execute($run, ['record_id' => ZOHO_ID, 'status' => 'Contacted'], []);
        $deleted = (new ZohoDeleteLeadNode)->execute($run, ['record_id' => ZOHO_ID], []);

        expect($updated)->toBe(['updated' => true, 'id' => ZOHO_ID, 'module' => 'Leads'])
            ->and(zohoBody('PUT /Leads/'.ZOHO_ID))->toBe(['data' => [['Lead_Status' => 'Contacted']]])
            ->and($deleted)->toBe(['deleted' => true, 'id' => ZOHO_ID, 'module' => 'Leads'])
            ->and(zohoQuery('DELETE /Leads'))->toBe(['ids' => ZOHO_ID]);
    });

    it('reads a lead with its lookups as names and ids, and says when it is not there', function () {
        fakeZoho(['GET /Leads/'.ZOHO_ID => ['data' => [zohoRecord()]], 'GET /Leads/4876876000007777777' => null]);
        $run = zohoRun();

        $found = (new ZohoGetLeadNode)->execute($run, ['record_id' => ZOHO_ID, 'read_fields' => ['Description']], []);

        expect($found)->toMatchArray(['found' => true, 'id' => ZOHO_ID, 'created_at' => '2026-03-01T10:00:00+00:00'])
            ->and((array) $found['fields'])->toMatchArray(['Owner' => 'Grace Hopper', 'Owner_id' => '4876876000000111111', 'Email' => 'ada@example.com'])
            ->and((new ZohoGetLeadNode)->execute($run, ['record_id' => '4876876000007777777'], []))->toBe(['found' => false, 'id' => '4876876000007777777'])
            ->and(zohoQuery('GET /Leads/'.ZOHO_ID)['fields'])->toContain('Last_Name')->toContain('Description');
    });

    it('searches by words or by one field, escaping the value, and treats "no content" as no matches', function () {
        fakeZoho(['GET /Contacts/search' => ['data' => [zohoRecord()]]]);
        $node = new ZohoSearchContactsNode;
        $run = zohoRun();

        $node->execute($run, ['field' => 'Email', 'operator' => 'contains', 'value' => 'ada (work), team:a'], []);
        expect(zohoQuery('GET /Contacts/search')['criteria'])->toBe('((Email:contains:ada \\(work\\)\\, team\\:a))');

        fakeZoho(['GET /Contacts/search' => null]);

        expect($node->execute($run, ['word' => 'nobody'], []))->toBe(['contacts' => [], 'count' => 0])
            ->and(fn () => $node->execute($run, [], []))->toThrow(InvalidArgumentException::class, 'Give words to search for')
            ->and(fn () => $node->execute($run, ['field' => 'Email) or (x', 'value' => 'a'], []))->toThrow(InvalidArgumentException::class, 'must be an API name');
    });

    it('creates or updates by the fields it matches on, and says which', function () {
        fakeZoho(['POST /Contacts/upsert' => zohoWritten(action: 'update')]);
        $node = new ZohoUpsertRecordNode;
        $run = zohoRun();

        $output = $node->execute($run, ['module' => 'Contacts', 'duplicate_fields' => ['Email'], 'fields' => '{"Last_Name": "Lovelace", "Email": "ada@example.com"}'], []);

        expect($output)->toBe(['id' => ZOHO_ID, 'module' => 'Contacts', 'created' => false])
            ->and(zohoBody('POST /Contacts/upsert')['duplicate_check_fields'])->toBe(['Email'])
            ->and(fn () => $node->execute($run, ['module' => 'Contacts', 'duplicate_fields' => ['Phone'], 'fields' => '{"Last_Name": "x"}'], []))->toThrow(InvalidArgumentException::class, 'must also be in Fields');
    });

    it('runs COQL in pages when it has no limit, and as written when it has one', function () {
        fakeZoho(['POST /coql' => fn (Request $request) => str_ends_with($request['select_query'], 'limit 200, 100')
            ? ['data' => [zohoRecord('4876876000000000003')], 'info' => ['more_records' => false]]
            : ['data' => array_map(fn (int $i): array => zohoRecord('48768760000000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)), range(1, 200)), 'info' => ['more_records' => true]]]);
        $node = new ZohoRunCoqlNode;
        $run = zohoRun();

        $output = $node->execute($run, ['query' => "select id, Last_Name from Leads where Email like '%@acme.com'", 'limit' => 300], []);

        expect($output['count'])->toBe(201);
        Http::assertSent(fn (Request $request): bool => ($request['select_query'] ?? '') === "select id, Last_Name from Leads where Email like '%@acme.com' limit 0, 200");

        fakeZoho(['POST /coql' => ['data' => [zohoRecord()], 'info' => ['more_records' => true]]]);
        $node->execute($run, ['query' => 'select id from Leads where id is not null limit 5'], []);

        Http::assertSentCount(1);
        expect(zohoBody('POST /coql')['select_query'])->toBe('select id from Leads where id is not null limit 5');
    });

    it('adds a note with the record as its parent', function () {
        fakeZoho(['POST /Notes' => zohoWritten('4876876000005555555')]);

        $output = (new ZohoAddNoteNode)->execute(zohoRun(), ['module' => 'Deals', 'record_id' => ZOHO_ID, 'title' => 'Call', 'content' => 'Sent the quote'], []);

        expect($output)->toBe(['id' => '4876876000005555555', 'module' => 'Deals', 'record_id' => ZOHO_ID])
            ->and(zohoBody('POST /Notes')['data'][0])->toBe(['Note_Title' => 'Call', 'Note_Content' => 'Sent the quote', 'Parent_Id' => ['id' => ZOHO_ID, 'module' => ['api_name' => 'Deals']]]);
    });

    it('says what Zoho said when it refuses the whole request', function () {
        fakeZoho(['GET /users' => fn () => Http::response(['code' => 'OAUTH_SCOPE_MISMATCH', 'message' => 'invalid oauth scope to access this API', 'status' => 'error'], 401)]);

        expect(fn () => (new ZohoGetMeNode)->execute(zohoRun(), [], []))->toThrow(RuntimeException::class, 'Zoho error: invalid oauth scope to access this API (OAUTH_SCOPE_MISMATCH).');
    });
});

describe('zoho dropdowns', function () {
    it('lists the modules you can create records in and the active users', function () {
        fakeZoho([
            'GET /settings/modules' => ['modules' => [['api_name' => 'Leads', 'plural_label' => 'Leads', 'api_supported' => true, 'creatable' => true], ['api_name' => 'Invoices__c', 'plural_label' => 'Invoices', 'api_supported' => true, 'creatable' => true, 'generated_type' => 'custom'], ['api_name' => 'Visits', 'plural_label' => 'Visits', 'api_supported' => true, 'creatable' => false]]],
            'GET /users' => ['users' => [['id' => '1', 'full_name' => 'Ada', 'email' => 'ada@acme.com']]],
        ]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = new NodeOptionsQuery($workspace, User::factory()->create(), [], 'csecret', credentialData: zohoData());
        $source = app(ZohoOptions::class);

        expect($source->load('zoho.modules', $query)->toArray()['options'])->toBe([['value' => 'Leads', 'label' => 'Leads'], ['value' => 'Invoices__c', 'label' => 'Invoices', 'description' => 'Custom']])
            ->and($source->load('zoho.users', $query)->toArray()['options'])->toBe([['value' => '1', 'label' => 'Ada', 'description' => 'ada@acme.com']]);
    });
});

/**
 * A published workflow whose first step is a Zoho record reader switched on as a
 * trigger, with its subscription synced against a faked Zoho.
 *
 * @param  array<string, mixed>  $routes
 */
function zohoTrigger(string $nodeType = 'zoho_list_new_leads', array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeZoho($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'zoho') ?? Connector::factory()->create(['key' => 'zoho']))
        ->create(['data' => zohoData(), 'created_by' => $owner->id]);

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

describe('zoho triggers', function () {
    it('polls new leads by creation time: a baseline first, then one run for each', function () {
        $trigger = zohoTrigger(sync: false);
        $poll = fn (array $ids) => fakeZoho(['POST /coql' => ['data' => array_map(fn (string $id): array => zohoRecord($id), $ids), 'info' => ['more_records' => false]]]);

        $poll(['4876876000000000001']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['4876876000000000003', '4876876000000000002', '4876876000000000001']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['4876876000000000002', '4876876000000000003'])
            ->and(zohoBody('POST /coql')['select_query'])->toContain('from Leads where Created_Time >= ')->toContain('order by Created_Time desc limit 0, 200');
    });

    it('filters changed deals by Modified_Time and makes every change its own event', function () {
        fakeZoho(['POST /coql' => ['data' => [zohoRecord()], 'info' => ['more_records' => false]]]);
        $node = new ZohoListChangedDealsNode;

        $node->execute(zohoRun(), [], []);

        expect(zohoBody('POST /coql')['select_query'])->toContain('from Deals where Modified_Time >= ')->toContain('order by Modified_Time desc')
            ->and($node->triggerItemId(['id' => '1', 'updated_at' => 'a']))->not->toBe($node->triggerItemId(['id' => '1', 'updated_at' => 'b']));
    });
});

describe('zoho instant delivery', function () {
    it('opens a notification channel for the module and change with a token URL, and starts a poll when it arrives', function () {
        $trigger = zohoTrigger(routes: ['POST /actions/watch' => ['watch' => [['code' => 'SUCCESS', 'status' => 'success', 'details' => []]]]]);
        $watch = ProviderWatch::query()->sole();
        $sent = zohoBody('POST /actions/watch')['watch'][0];

        expect($watch->resource_key)->toBe('Leads.create')
            ->and($watch->external_id)->toBe($sent['channel_id'])
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($sent['events'])->toBe(['Leads.create'])
            ->and($sent['notify_url'])->toBe(url('/api/hooks/apps/zoho').'?token='.$watch->secret)
            ->and($sent['channel_expiry'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/');

        $body = ['module' => 'Leads', 'operation' => 'insert', 'ids' => [ZOHO_ID], 'channel_id' => $watch->external_id, 'server_time' => 1772100000000, 'token' => 'x'];
        test()->postJson('/api/hooks/apps/zoho?token='.$watch->secret, $body)->assertOk();
        test()->postJson('/api/hooks/apps/zoho?token='.$watch->secret, $body)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('watches for edits on a changed-deals trigger', function () {
        zohoTrigger('zoho_list_changed_deals', ['POST /actions/watch' => ['watch' => [['status' => 'success']]]]);

        expect(ProviderWatch::query()->sole()->resource_key)->toBe('Deals.edit');
    });

    it('refuses a wrong or missing token and is closed while the switch is off', function () {
        zohoTrigger(routes: ['POST /actions/watch' => ['watch' => [['status' => 'success']]]]);
        $watch = ProviderWatch::query()->sole();
        $body = ['module' => 'Leads', 'operation' => 'insert', 'ids' => [ZOHO_ID], 'server_time' => 1];

        test()->postJson('/api/hooks/apps/zoho?token=nope', $body)->assertUnauthorized();
        test()->postJson('/api/hooks/apps/zoho', $body)->assertUnauthorized();
        config(['triggers.push.zoho.enabled' => false]);
        test()->postJson('/api/hooks/apps/zoho?token='.$watch->secret, $body)->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('says so when Zoho refuses the channel, and drops the watch', function () {
        $trigger = zohoTrigger(routes: ['POST /actions/watch' => ['watch' => [['code' => 'INVALID_DATA', 'message' => 'invalid notify_url', 'status' => 'error']]]]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)->and($trigger->subscription->last_error)->toContain('invalid notify_url')->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('extends the channel, makes it again when Zoho lost it, and closes it with the watch', function () {
        zohoTrigger(routes: ['POST /actions/watch' => ['watch' => [['status' => 'success']]]]);
        $watch = ProviderWatch::query()->sole();
        $source = app(ZohoSource::class);
        $channel = $watch->external_id;

        fakeZoho(['PUT /actions/watch' => ['watch' => [['status' => 'success']]]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe($channel)->and(zohoBody('PUT /actions/watch')['watch'][0]['channel_id'])->toBe($channel);

        fakeZoho(['PUT /actions/watch' => ['watch' => [['code' => 'INVALID_DATA', 'status' => 'error']]], 'POST /actions/watch' => ['watch' => [['status' => 'success']]]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->not->toBe($channel);

        fakeZoho(['DELETE /actions/watch' => ['watch' => [['status' => 'success']]]]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_contains($request->url(), 'channel_ids='));
    });
});
