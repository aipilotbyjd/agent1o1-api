<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Salesforce\AbstractSalesforceNode;
use App\Nodes\Integrations\Salesforce\SalesforceCreateLeadNode;
use App\Nodes\Integrations\Salesforce\SalesforceCreateRecordNode;
use App\Nodes\Integrations\Salesforce\SalesforceDeleteRecordNode;
use App\Nodes\Integrations\Salesforce\SalesforceDescribeObjectNode;
use App\Nodes\Integrations\Salesforce\SalesforceFindRecordNode;
use App\Nodes\Integrations\Salesforce\SalesforceGetOrgNode;
use App\Nodes\Integrations\Salesforce\SalesforceGetRecordNode;
use App\Nodes\Integrations\Salesforce\SalesforceListChangedOpportunitiesNode;
use App\Nodes\Integrations\Salesforce\SalesforceRunQueryNode;
use App\Nodes\Integrations\Salesforce\SalesforceSearchNode;
use App\Nodes\Integrations\Salesforce\SalesforceUpdateOpportunityNode;
use App\Nodes\Integrations\Salesforce\SalesforceUpsertRecordNode;
use App\Services\Triggers\Push\PushSources;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\SalesforceOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const SF_ID = '001Hn00001AbCdEFGH';

/**
 * A run with a connected Salesforce org in its workspace.
 *
 * @param  array<string, mixed>  $data
 */
function salesforceRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'salesforce') ?? Connector::factory()->create(['key' => 'salesforce']))->id,
        'data' => ['domain' => 'acme.my.salesforce.com', 'client_id' => 'cid', 'api_key' => 'csecret', ...$data],
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Salesforce: the login at the org's token endpoint, and the REST API on the
 * instance it names. A key is "METHOD /path" under /services/data/v62.0 (or a full
 * /services path); the value is the JSON body, or a closure returning a response. A
 * route nobody answers is an error, so a node that calls something unexpected fails.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeSalesforce(array $routes, string $instance = 'https://acme.my.salesforce.com'): void
{
    Http::swap(new Factory);
    Http::fake(['*salesforce.com/*' => function (Request $request) use ($routes, $instance) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/services/oauth2/token') {
            return Http::response(['access_token' => 'sf-session', 'instance_url' => $instance, 'token_type' => 'Bearer']);
        }

        $key = $request->method().' '.preg_replace('~^/services/data/v62\.0~', '', $path);
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response([['message' => "Nothing fakes {$key}", 'errorCode' => 'NOT_FOUND']], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

/**
 * The request made to a route.
 */
function salesforceRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/services/data/v62\.0~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function salesforceBody(string $route): array
{
    return (array) json_decode(salesforceRequest($route)->body(), true);
}

/**
 * @return array<string, string>
 */
function salesforceQuery(string $route): array
{
    parse_str((string) parse_url(salesforceRequest($route)->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function salesforceRecord(string $type = 'Opportunity', string $id = SF_ID, array $extra = []): array
{
    return ['attributes' => ['type' => $type, 'url' => "/services/data/v62.0/sobjects/{$type}/{$id}"], 'Id' => $id, 'Name' => 'Acme annual plan', 'CreatedDate' => '2026-03-01T10:00:00.000+0000', 'SystemModstamp' => '2026-03-01T11:00:00.000+0000', ...$extra];
}

describe('salesforce nodes', function () {
    it('registers the nodes under the salesforce connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'salesforce_'));

        expect(count($types))->toBeGreaterThanOrEqual(33);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractSalesforceNode::class)->and($registry->resolve($type)->category())->toBe('salesforce');
        }
    });

    it('logs in once with the client credentials and calls the instance Salesforce names', function () {
        fakeSalesforce(['GET /limits' => ['DailyApiRequests' => ['Max' => 15000, 'Remaining' => 14990]]], 'https://acme.my.salesforce.com');
        $run = salesforceRun();

        $first = (new SalesforceGetOrgNode)->execute($run, [], []);
        (new SalesforceGetOrgNode)->execute($run, [], []);

        expect($first)->toBe(['instance' => 'https://acme.my.salesforce.com', 'api_requests_max' => 15000, 'api_requests_left' => 14990]);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://acme.my.salesforce.com/services/oauth2/token' && $request['grant_type'] === 'client_credentials' && $request['client_id'] === 'cid' && $request['client_secret'] === 'csecret');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/services/data/v62.0/limits') && $request->hasHeader('Authorization', 'Bearer sf-session'));
    });

    it('refuses a domain that is not Salesforce, an instance that is not Salesforce, and a login Salesforce refuses', function () {
        fakeSalesforce([]);

        expect(fn () => (new SalesforceGetOrgNode)->execute(salesforceRun(['domain' => 'evil.example.com']), [], []))->toThrow(InvalidArgumentException::class, 'My Domain')
            ->and(fn () => (new SalesforceGetOrgNode)->execute(salesforceRun(['domain' => 'acme.my.salesforce.com.evil.com']), [], []))->toThrow(InvalidArgumentException::class, 'My Domain')
            ->and(fn () => (new SalesforceGetOrgNode)->execute(salesforceRun(['client_id' => '']), [], []))->toThrow(InvalidArgumentException::class, 'client id and client secret');

        fakeSalesforce([], 'https://evil.example.com');

        expect(fn () => (new SalesforceGetOrgNode)->execute(salesforceRun(['client_id' => 'other']), [], []))->toThrow(RuntimeException::class, 'not Salesforce');

        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['error' => 'invalid_client', 'error_description' => 'invalid client credentials'], 400)]);

        expect(fn () => (new SalesforceGetOrgNode)->execute(salesforceRun(['client_id' => 'bad']), [], []))->toThrow(RuntimeException::class, 'Salesforce refused the login: invalid client credentials');
    });

    it('creates a lead from its fields and the JSON map', function () {
        fakeSalesforce(['POST /sobjects/Lead' => ['id' => '00QHn00000XyZaBCDE', 'success' => true, 'errors' => []]]);

        $output = (new SalesforceCreateLeadNode)->execute(salesforceRun(), ['last_name' => 'Lovelace', 'company' => 'Acme', 'email' => 'ada@example.com', 'owner_id' => '005Hn00000AbCdEFGH', 'fields' => '{"Industry": "Retail", "Region__c": "EMEA"}'], []);

        expect($output)->toBe(['id' => '00QHn00000XyZaBCDE', 'object' => 'Lead', 'success' => true])
            ->and(salesforceBody('POST /sobjects/Lead'))->toBe(['Industry' => 'Retail', 'Region__c' => 'EMEA', 'LastName' => 'Lovelace', 'Company' => 'Acme', 'Email' => 'ada@example.com', 'OwnerId' => '005Hn00000AbCdEFGH']);
    });

    it('checks object, field and record names before calling Salesforce', function () {
        fakeSalesforce([]);
        $run = salesforceRun();

        expect(fn () => (new SalesforceCreateRecordNode)->execute($run, ['object' => 'Lead/../User', 'fields' => '{"A": 1}'], []))->toThrow(InvalidArgumentException::class, 'must be an API name')
            ->and(fn () => (new SalesforceCreateRecordNode)->execute($run, ['object' => 'Lead', 'fields' => '{"A b": 1}'], []))->toThrow(InvalidArgumentException::class, 'must be an API name')
            ->and(fn () => (new SalesforceCreateRecordNode)->execute($run, ['object' => 'Lead', 'fields' => '{}'], []))->toThrow(InvalidArgumentException::class, 'at least one field')
            ->and(fn () => (new SalesforceUpdateOpportunityNode)->execute($run, ['record_id' => '006/../x', 'stage' => 'Won'], []))->toThrow(InvalidArgumentException::class, 'not a Salesforce id')
            ->and(fn () => (new SalesforceUpdateOpportunityNode)->execute($run, ['record_id' => SF_ID], []))->toThrow(InvalidArgumentException::class, 'Change at least one field')
            ->and(fn () => (new SalesforceDeleteRecordNode)->execute($run, ['object' => 'Lead', 'record_id' => ''], []))->toThrow(InvalidArgumentException::class, 'The record is required');
    });

    it('updates an opportunity by PATCH, with the amount as a number', function () {
        fakeSalesforce(['PATCH /sobjects/Opportunity/'.SF_ID => null]);

        $output = (new SalesforceUpdateOpportunityNode)->execute(salesforceRun(), ['record_id' => SF_ID, 'stage' => 'Closed Won', 'amount' => '4800'], []);

        expect($output)->toBe(['updated' => true, 'id' => SF_ID, 'object' => 'Opportunity'])
            ->and(salesforceBody('PATCH /sobjects/Opportunity/'.SF_ID))->toBe(['Amount' => 4800, 'StageName' => 'Closed Won']);
    });

    it('runs a SOQL query across pages, and only a SELECT', function () {
        fakeSalesforce([
            'GET /query' => ['records' => [salesforceRecord('Account', '001Hn00001AaAaAaAa'), salesforceRecord('Account', '001Hn00001BbBbBbBb')], 'done' => false, 'nextRecordsUrl' => '/services/data/v62.0/query/01gNEXT-2000'],
            'GET /query/01gNEXT-2000' => ['records' => [salesforceRecord('Account', '001Hn00001CcCcCcCc')], 'done' => true],
        ]);
        $node = new SalesforceRunQueryNode;
        $run = salesforceRun();

        $output = $node->execute($run, ['query' => 'SELECT Id, Name FROM Account', 'limit' => 100], []);

        expect($output['count'])->toBe(3)->and($output['records'][0])->toMatchArray(['id' => '001Hn00001AaAaAaAa', 'object' => 'Account'])
            ->and((array) $output['records'][0]['fields'])->toBe(['Id' => '001Hn00001AaAaAaAa', 'Name' => 'Acme annual plan', 'CreatedDate' => '2026-03-01T10:00:00.000+0000', 'SystemModstamp' => '2026-03-01T11:00:00.000+0000'])
            ->and(fn () => $node->execute($run, ['query' => 'DELETE FROM Account'], []))->toThrow(InvalidArgumentException::class, 'must start with SELECT');
    });

    it('follows a paging link only inside the data API', function () {
        fakeSalesforce(['GET /query' => ['records' => [salesforceRecord()], 'done' => false, 'nextRecordsUrl' => 'https://evil.example.com/steal']]);

        $output = (new SalesforceRunQueryNode)->execute(salesforceRun(), ['query' => 'SELECT Id FROM Account', 'limit' => 100], []);

        expect($output['count'])->toBe(1);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'evil.example.com'));
    });

    it('finds a record by a field, quoting the value so it can\'t change the query', function () {
        fakeSalesforce(['GET /query' => ['records' => [salesforceRecord('Contact', '003Hn00001AbCdEFGH')]]]);
        $run = salesforceRun();

        $found = (new SalesforceFindRecordNode)->execute($run, ['object' => 'Contact', 'field' => 'Email', 'value' => "o'brien' OR Name != '", 'read_fields' => ['Department']], []);

        expect($found['found'])->toBeTrue()->and($found['id'])->toBe('003Hn00001AbCdEFGH')
            ->and(salesforceQuery('GET /query')['q'])->toBe("SELECT Id, FirstName, LastName, Email, Phone, Title, AccountId, OwnerId, CreatedDate, SystemModstamp, Department FROM Contact WHERE Email = 'o\\'brien\\' OR Name != \\'' ORDER BY CreatedDate DESC LIMIT 1")
            ->and(fn () => (new SalesforceFindRecordNode)->execute($run, ['object' => 'Contact', 'field' => 'Email = 1 OR Name', 'value' => 'a'], []))->toThrow(InvalidArgumentException::class, 'The field must be an API name');

        fakeSalesforce(['GET /query' => ['records' => []]]);

        expect((new SalesforceFindRecordNode)->execute($run, ['object' => 'Contact', 'field' => 'Email', 'value' => 'none'], []))->toBe(['found' => false]);
    });

    it('reads a record by id and says when it is not there', function () {
        fakeSalesforce(['GET /sobjects/Account/'.SF_ID => salesforceRecord('Account'), 'GET /sobjects/Account/001Hn00001ZzZzZzZz' => fn () => Http::response([['message' => 'The requested resource does not exist', 'errorCode' => 'NOT_FOUND']], 404)]);
        $run = salesforceRun();

        expect((new SalesforceGetRecordNode)->execute($run, ['object' => 'Account', 'record_id' => SF_ID, 'read_fields' => ['Name']], []))->toMatchArray(['found' => true, 'id' => SF_ID, 'object' => 'Account'])
            ->and((new SalesforceGetRecordNode)->execute($run, ['object' => 'Account', 'record_id' => '001Hn00001ZzZzZzZz'], []))->toBe(['found' => false, 'id' => '001Hn00001ZzZzZzZz']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'fields=Name'));
    });

    it('creates or updates by an external id and says which', function () {
        fakeSalesforce(['PATCH /sobjects/Account/External_Id__c/ACME-1' => fn () => Http::response(['id' => SF_ID, 'success' => true, 'created' => true], 201)]);
        $node = new SalesforceUpsertRecordNode;

        $output = $node->execute(salesforceRun(), ['object' => 'Account', 'external_field' => 'External_Id__c', 'external_value' => 'ACME-1', 'fields' => '{"Name": "Acme"}'], []);

        expect($output)->toBe(['created' => true, 'id' => SF_ID, 'object' => 'Account'])->and(salesforceBody('PATCH /sobjects/Account/External_Id__c/ACME-1'))->toBe(['Name' => 'Acme']);

        fakeSalesforce(['PATCH /sobjects/Account/External_Id__c/ACME-1' => null]);

        expect($node->execute(salesforceRun(['client_id' => 'cid2']), ['object' => 'Account', 'external_field' => 'External_Id__c', 'external_value' => 'ACME-1', 'fields' => '{"Name": "Acme"}'], [])['created'])->toBeFalse();
    });

    it('searches with SOSL, escaping its special characters', function () {
        fakeSalesforce(['GET /search' => ['searchRecords' => [salesforceRecord('Contact', '003Hn00001AbCdEFGH')]]]);

        $output = (new SalesforceSearchNode)->execute(salesforceRun(), ['term' => 'acme {inc} (eu)', 'objects' => ['Contact', 'Account'], 'limit' => 5], []);

        expect($output['count'])->toBe(1)
            ->and(salesforceQuery('GET /search')['q'])->toBe('FIND {acme \\{inc\\} \\(eu\\)} IN ALL FIELDS RETURNING Contact(Id,FirstName,LastName,Email,Phone,Title LIMIT 5), Account(Id,Name,Website,Industry,Phone,Type LIMIT 5)');
    });

    it('describes an object and deletes a record', function () {
        fakeSalesforce([
            'GET /sobjects/Opportunity/describe' => ['name' => 'Opportunity', 'label' => 'Opportunity', 'fields' => [['name' => 'StageName', 'label' => 'Stage', 'type' => 'picklist', 'nillable' => false, 'createable' => true, 'defaultedOnCreate' => false, 'picklistValues' => [['value' => 'Prospecting', 'active' => true], ['value' => 'Old', 'active' => false]]]]],
            'DELETE /sobjects/Lead/'.SF_ID => null,
        ]);
        $run = salesforceRun();

        $described = (new SalesforceDescribeObjectNode)->execute($run, ['object' => 'Opportunity'], []);

        expect($described['fields'][0])->toBe(['name' => 'StageName', 'label' => 'Stage', 'type' => 'picklist', 'required' => true, 'values' => ['Prospecting']])
            ->and((new SalesforceDeleteRecordNode)->execute($run, ['object' => 'Lead', 'record_id' => SF_ID], []))->toBe(['deleted' => true, 'id' => SF_ID, 'object' => 'Lead']);
    });

    it('says what Salesforce said when it refuses', function () {
        fakeSalesforce(['POST /sobjects/Lead' => fn () => Http::response([['message' => 'Required fields are missing: [Company]', 'errorCode' => 'REQUIRED_FIELD_MISSING', 'fields' => ['Company']]], 400)]);

        expect(fn () => (new SalesforceCreateLeadNode)->execute(salesforceRun(), ['last_name' => 'Lovelace'], []))->toThrow(RuntimeException::class, 'Salesforce error: Required fields are missing: [Company] (REQUIRED_FIELD_MISSING).');
    });
});

describe('salesforce dropdowns', function () {
    it('lists the objects you can create and the active users', function () {
        fakeSalesforce([
            'GET /sobjects' => ['sobjects' => [['name' => 'Account', 'label' => 'Account', 'createable' => true, 'queryable' => true], ['name' => 'Invoice__c', 'label' => 'Invoice', 'custom' => true, 'createable' => true, 'queryable' => true], ['name' => 'AccountHistory', 'label' => 'History', 'createable' => false, 'queryable' => true]]],
            'GET /query' => ['records' => [['Id' => '005Hn00000AbCdEFGH', 'Name' => 'Ada Lovelace', 'Email' => 'ada@acme.com']]],
        ]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = new NodeOptionsQuery($workspace, User::factory()->create(), [], 'csecret', credentialData: ['domain' => 'acme.my.salesforce.com', 'client_id' => 'cid', 'api_key' => 'csecret']);
        $source = app(SalesforceOptions::class);

        expect($source->load('salesforce.objects', $query)->toArray()['options'])->toBe([['value' => 'Account', 'label' => 'Account'], ['value' => 'Invoice__c', 'label' => 'Invoice', 'description' => 'Custom']])
            ->and($source->load('salesforce.users', $query)->toArray()['options'])->toBe([['value' => '005Hn00000AbCdEFGH', 'label' => 'Ada Lovelace', 'description' => 'ada@acme.com']]);
    });
});

describe('salesforce triggers', function () {
    it('polls changed opportunities by SystemModstamp: a baseline first, then one run for each change', function () {
        Queue::fake([FireTriggerEvent::class]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'salesforce') ?? Connector::factory()->create(['key' => 'salesforce']))
            ->create(['data' => ['domain' => 'acme.my.salesforce.com', 'client_id' => 'cid', 'api_key' => 'csecret'], 'created_by' => $owner->id]);

        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'salesforce_list_changed_opportunities', 'config' => ['credential_id' => $credential->id, 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);

        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        $poll = fn (array $records) => fakeSalesforce(['GET /query' => ['records' => $records]]);

        $poll([salesforceRecord('Opportunity', '006Hn00001AaAaAaAa')]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([
            salesforceRecord('Opportunity', '006Hn00001AaAaAaAa', ['SystemModstamp' => '2026-03-01T12:00:00.000+0000']),
            salesforceRecord('Opportunity', '006Hn00001BbBbBbBb'),
            salesforceRecord('Opportunity', '006Hn00001AaAaAaAa'),
        ]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['006Hn00001AaAaAaAa', '006Hn00001BbBbBbBb'])
            ->and(salesforceQuery('GET /query')['q'])->toContain('FROM Opportunity WHERE SystemModstamp >= ')->toContain('ORDER BY SystemModstamp DESC');
    });

    it('makes every change its own event and has no push', function () {
        $node = new SalesforceListChangedOpportunitiesNode;

        expect($node->triggerItemId(['id' => 'x', 'updated_at' => 'a']))->not->toBe($node->triggerItemId(['id' => 'x', 'updated_at' => 'b']))
            ->and(app(PushSources::class)->source('salesforce'))->toBeNull();
    });
});
