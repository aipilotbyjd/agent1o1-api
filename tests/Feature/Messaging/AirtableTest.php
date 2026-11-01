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
use App\Nodes\Integrations\Airtable\AbstractAirtableNode;
use App\Nodes\Integrations\Airtable\AirtableAddCommentNode;
use App\Nodes\Integrations\Airtable\AirtableCreateRecordNode;
use App\Nodes\Integrations\Airtable\AirtableCreateRecordsNode;
use App\Nodes\Integrations\Airtable\AirtableDeleteRecordNode;
use App\Nodes\Integrations\Airtable\AirtableFindRecordNode;
use App\Nodes\Integrations\Airtable\AirtableListRecordsNode;
use App\Nodes\Integrations\Airtable\AirtableListUpdatedRecordsNode;
use App\Nodes\Integrations\Airtable\AirtableUpdateRecordNode;
use App\Nodes\Integrations\Airtable\AirtableUpsertRecordNode;
use App\Services\Triggers\Push\AirtableSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\AirtableOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const AIRTABLE_BASE = 'appAbc123def456';
const AIRTABLE_REC = 'recAbc123def456';

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function airtableConfig(array $config = []): array
{
    return ['access_token' => 'pat_test', 'base_id' => AIRTABLE_BASE, 'table' => 'Campaigns', ...$config];
}

/**
 * Answer Airtable. A key is "METHOD /path" under /v0, decoded (a trailing * takes
 * any id); the value is the JSON body, or a closure returning a response. A route
 * nobody answers is a 404, so a node that calls something unexpected fails the
 * test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeAirtable(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.airtable.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.urldecode(preg_replace('~^/v0~', '', (string) parse_url($request->url(), PHP_URL_PATH)));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['error' => ['type' => 'NOT_FOUND', 'message' => "Nothing fakes {$key}"]], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : Http::response($answer ?? []);
    }]);
}

/**
 * The request made to a route.
 */
function airtableRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.urldecode(preg_replace('~^/v0~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH))) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function airtableBody(string $route): array
{
    return (array) json_decode(airtableRequest($route)->body(), true);
}

/**
 * The query string of a route's request, decoded, as a list of name=value parts.
 *
 * @return list<string>
 */
function airtableQuery(string $route): array
{
    return array_map('urldecode', array_values(array_filter(explode('&', (string) parse_url(airtableRequest($route)->url(), PHP_URL_QUERY)))));
}

/**
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function airtableRecord(string $id = AIRTABLE_REC, array $fields = ['Name' => 'Spring launch'], ?string $created = null): array
{
    return ['id' => $id, 'createdTime' => $created ?? now()->subMinutes(10)->toIso8601String(), 'fields' => $fields];
}

describe('airtable nodes', function () {
    it('registers the nodes under the airtable connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'airtable_'));

        expect(count($types))->toBeGreaterThanOrEqual(15);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractAirtableNode::class)->and($registry->resolve($type)->category())->toBe('airtable');
        }
    });

    it('creates a record with a bearer token, a typecast and the table name encoded', function () {
        fakeAirtable(['POST /'.AIRTABLE_BASE.'/Campaigns Q1' => airtableRecord()]);

        $output = (new AirtableCreateRecordNode)->execute(Run::factory()->create(), airtableConfig(['table' => 'Campaigns Q1', 'fields' => '{"Name": "Spring launch", "Budget": "1200"}']), []);

        expect($output['id'])->toBe(AIRTABLE_REC)->and((array) $output['fields'])->toBe(['Name' => 'Spring launch'])
            ->and(airtableBody('POST /'.AIRTABLE_BASE.'/Campaigns Q1'))->toBe(['fields' => ['Name' => 'Spring launch', 'Budget' => '1200'], 'typecast' => true]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer pat_test'));
    });

    it('checks ids and fields before calling Airtable', function () {
        fakeAirtable([]);
        $run = Run::factory()->create();
        $node = new AirtableCreateRecordNode;

        expect(fn () => $node->execute($run, airtableConfig(['base_id' => '../meta', 'fields' => '{"a": 1}']), []))->toThrow(InvalidArgumentException::class, 'not an Airtable base id')
            ->and(fn () => $node->execute($run, airtableConfig(['table' => '', 'fields' => '{"a": 1}']), []))->toThrow(InvalidArgumentException::class, 'The table is required')
            ->and(fn () => $node->execute($run, airtableConfig(['fields' => '["a"]']), []))->toThrow(InvalidArgumentException::class, 'JSON object')
            ->and(fn () => $node->execute($run, airtableConfig(['fields' => '{}']), []))->toThrow(InvalidArgumentException::class, 'JSON object')
            ->and(fn () => (new AirtableUpdateRecordNode)->execute($run, airtableConfig(['record_id' => 'abc', 'fields' => '{"a": 1}']), []))->toThrow(InvalidArgumentException::class, 'not an Airtable record id');
        Http::assertNothingSent();
    });

    it('creates many records ten at a time', function () {
        fakeAirtable(['POST /'.AIRTABLE_BASE.'/Campaigns' => fn (Request $request) => ['records' => array_map(fn (array $row): array => airtableRecord('rec'.Str::random(8), $row['fields']), json_decode($request->body(), true)['records'])]]);
        $rows = array_map(fn (int $i): array => ['Name' => "Row {$i}"], range(1, 23));

        $output = (new AirtableCreateRecordsNode)->execute(Run::factory()->create(), airtableConfig(['records' => json_encode($rows)]), []);

        expect($output['count'])->toBe(23);
        Http::assertSentCount(3);
        expect(fn () => (new AirtableCreateRecordsNode)->execute(Run::factory()->create(), airtableConfig(['records' => '[]']), []))->toThrow(InvalidArgumentException::class, 'between 1 and 500');
    });

    it('updates only the fields given', function () {
        fakeAirtable(['PATCH /'.AIRTABLE_BASE.'/Campaigns/'.AIRTABLE_REC => airtableRecord(fields: ['Status' => 'Done'])]);

        $output = (new AirtableUpdateRecordNode)->execute(Run::factory()->create(), airtableConfig(['record_id' => AIRTABLE_REC, 'fields' => ['Status' => 'Done'], 'typecast' => false]), []);

        expect((array) $output['fields'])->toBe(['Status' => 'Done'])->and(airtableBody('PATCH /'.AIRTABLE_BASE.'/Campaigns/'.AIRTABLE_REC))->toBe(['fields' => ['Status' => 'Done'], 'typecast' => false]);
    });

    it('creates or updates by the fields it matches on, and says which it did', function () {
        fakeAirtable(['PATCH /'.AIRTABLE_BASE.'/Campaigns' => ['records' => [airtableRecord()], 'createdRecords' => [AIRTABLE_REC], 'updatedRecords' => []]]);
        $node = new AirtableUpsertRecordNode;
        $run = Run::factory()->create();

        $output = $node->execute($run, airtableConfig(['fields' => '{"Email": "ada@example.com", "Name": "Ada"}', 'match_fields' => ['Email']]), []);

        expect($output['created'])->toBeTrue()
            ->and(airtableBody('PATCH /'.AIRTABLE_BASE.'/Campaigns')['performUpsert'])->toBe(['fieldsToMergeOn' => ['Email']])
            ->and(fn () => $node->execute($run, airtableConfig(['fields' => '{"Name": "Ada"}', 'match_fields' => ['Email']]), []))->toThrow(InvalidArgumentException::class, 'must also be in Fields')
            ->and(fn () => $node->execute($run, airtableConfig(['fields' => '{"Name": "Ada"}', 'match_fields' => []]), []))->toThrow(InvalidArgumentException::class, 'one to three fields');
    });

    it('reads records page by page with the view, formula, sort and fields as Airtable spells them', function () {
        fakeAirtable(['GET /'.AIRTABLE_BASE.'/Campaigns' => fn (Request $request) => str_contains($request->url(), 'offset=itr2')
            ? ['records' => [airtableRecord('recC')]]
            : ['records' => [airtableRecord('recA'), airtableRecord('recB')], 'offset' => 'itr2']]);

        $output = (new AirtableListRecordsNode)->execute(Run::factory()->create(), airtableConfig(['view' => 'Grid view', 'formula' => "{Status} = 'Todo'", 'sort_field' => 'Name', 'sort_direction' => 'desc', 'only_fields' => ['Name', 'Status'], 'limit' => 150]), []);

        expect(array_column($output['records'], 'id'))->toBe(['recA', 'recB', 'recC']);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => ! str_contains($request->url(), 'offset=') && in_array("filterByFormula={Status} = 'Todo'", array_map('urldecode', explode('&', (string) parse_url($request->url(), PHP_URL_QUERY))), true) && str_contains(urldecode($request->url()), 'sort[0][field]=Name') && str_contains(urldecode($request->url()), 'sort[0][direction]=desc') && str_contains(urldecode($request->url()), 'fields[]=Name') && str_contains(urldecode($request->url()), 'fields[]=Status'));
    });

    it('finds a record by a field\'s value, quoting the value so it can\'t change the formula', function () {
        fakeAirtable(['GET /'.AIRTABLE_BASE.'/Campaigns' => ['records' => [airtableRecord()]]]);
        $run = Run::factory()->create();

        $found = (new AirtableFindRecordNode)->execute($run, airtableConfig(['field' => 'Email', 'value' => "o'brien') OR (1=1"]), []);

        expect($found['found'])->toBeTrue()->and($found['id'])->toBe(AIRTABLE_REC)
            ->and(airtableQuery('GET /'.AIRTABLE_BASE.'/Campaigns'))->toContain("filterByFormula={Email} = 'o\\'brien\\') OR (1=1'", 'maxRecords=1')
            ->and(fn () => (new AirtableFindRecordNode)->execute($run, airtableConfig(['field' => 'Email} = 1 OR {x', 'value' => 'a']), []))->toThrow(InvalidArgumentException::class, 'without braces');

        fakeAirtable(['GET /'.AIRTABLE_BASE.'/Campaigns' => ['records' => []]]);

        expect((new AirtableFindRecordNode)->execute($run, airtableConfig(['field' => 'Email', 'value' => 'none']), []))->toBe(['found' => false]);
    });

    it('deletes and comments on a record', function () {
        fakeAirtable([
            'DELETE /'.AIRTABLE_BASE.'/Campaigns/'.AIRTABLE_REC => ['deleted' => true, 'id' => AIRTABLE_REC],
            'POST /'.AIRTABLE_BASE.'/Campaigns/'.AIRTABLE_REC.'/comments' => ['id' => 'com1', 'text' => 'Looks good', 'author' => ['email' => 'ada@example.com'], 'createdTime' => '2026-03-01T10:00:00.000Z'],
        ]);
        $run = Run::factory()->create();

        expect((new AirtableDeleteRecordNode)->execute($run, airtableConfig(['record_id' => AIRTABLE_REC]), []))->toBe(['deleted' => true, 'record_id' => AIRTABLE_REC])
            ->and((new AirtableAddCommentNode)->execute($run, airtableConfig(['record_id' => AIRTABLE_REC, 'text' => 'Looks good']), []))->toBe(['id' => 'com1', 'record_id' => AIRTABLE_REC, 'text' => 'Looks good', 'author' => 'ada@example.com', 'created_at' => '2026-03-01T10:00:00.000Z']);
    });

    it('says what Airtable said when it refuses', function () {
        fakeAirtable(['POST /'.AIRTABLE_BASE.'/Campaigns' => fn () => Http::response(['error' => ['type' => 'INVALID_VALUE_FOR_COLUMN', 'message' => 'Field "Budget" cannot accept the provided value']], 422)]);

        expect(fn () => (new AirtableCreateRecordNode)->execute(Run::factory()->create(), airtableConfig(['fields' => '{"Budget": "lots"}']), []))->toThrow(RuntimeException::class, 'Airtable error: Field "Budget" cannot accept the provided value (INVALID_VALUE_FOR_COLUMN).');
    });
});

describe('airtable dropdowns', function () {
    it('lists bases with the next page and the tables of a base', function () {
        fakeAirtable([
            'GET /meta/bases' => ['bases' => [['id' => AIRTABLE_BASE, 'name' => 'Marketing']], 'offset' => 'next1'],
            'GET /meta/bases/'.AIRTABLE_BASE.'/tables' => ['tables' => [['id' => 'tblAaa', 'name' => 'Campaigns']]],
        ]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = fn (array $config = []): NodeOptionsQuery => new NodeOptionsQuery($workspace, User::factory()->create(), $config, 'pat_test');
        $source = app(AirtableOptions::class);

        expect($source->load('airtable.bases', $query())->toArray())->toBe(['options' => [['value' => AIRTABLE_BASE, 'label' => 'Marketing']], 'next_cursor' => 'next1'])
            ->and($source->load('airtable.tables', $query(['base_id' => AIRTABLE_BASE]))->toArray()['options'])->toBe([['value' => 'tblAaa', 'label' => 'Campaigns']])
            ->and($source->load('airtable.tables', $query())->toArray()['options'])->toBe([]);
    });
});

/**
 * A published workflow whose first step is an Airtable record reader switched on
 * as a trigger, with its subscription synced against a faked Airtable.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 */
function airtableTrigger(string $nodeType = 'airtable_list_new_records', array $nodeConfig = [], array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeAirtable($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'airtable') ?? Connector::factory()->create(['key' => 'airtable']))
        ->create(['data' => ['api_key' => 'pat_test'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, 'base_id' => AIRTABLE_BASE, 'table' => 'Campaigns', ...$nodeConfig]],
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

describe('airtable triggers', function () {
    it('polls new records: a baseline first, then one run for each', function () {
        $trigger = airtableTrigger(sync: false);
        $poll = fn (array $ids) => fakeAirtable(['GET /'.AIRTABLE_BASE.'/Campaigns' => ['records' => array_map(fn (string $id): array => airtableRecord($id), $ids)]]);

        $poll(['recA']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['recC', 'recB', 'recA']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['recB', 'recC']);
    });

    it('filters by creation or modification time in the formula, and fires again when a record\'s content changes', function () {
        fakeAirtable(['GET /'.AIRTABLE_BASE.'/Campaigns' => ['records' => [airtableRecord()]]]);
        $node = new AirtableListUpdatedRecordsNode;

        $node->execute(Run::factory()->create(), airtableConfig(), []);

        expect(implode('&', airtableQuery('GET /'.AIRTABLE_BASE.'/Campaigns')))->toContain("filterByFormula=IS_AFTER(LAST_MODIFIED_TIME(), '")
            ->and($node->triggerItemId(['id' => 'recA', 'fields' => ['Status' => 'Todo']]))->not->toBe($node->triggerItemId(['id' => 'recA', 'fields' => ['Status' => 'Done']]))
            ->and($node->triggerItemId(['id' => 'recA', 'fields' => ['Status' => 'Todo']]))->toBe($node->triggerItemId(['id' => 'recA', 'fields' => ['Status' => 'Todo']]));
    });
});

describe('airtable instant delivery', function () {
    it('creates a webhook on the base with a token URL, and starts a poll when a ping arrives', function () {
        $trigger = airtableTrigger(routes: ['POST /bases/'.AIRTABLE_BASE.'/webhooks' => ['id' => 'achWh1', 'macSecretBase64' => 'c2VjcmV0', 'expirationTime' => '2026-03-08T00:00:00.000Z']]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe(AIRTABLE_BASE)
            ->and($watch->external_id)->toBe('achWh1')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(airtableBody('POST /bases/'.AIRTABLE_BASE.'/webhooks'))->toBe(['notificationUrl' => url('/api/hooks/apps/airtable').'?token='.$watch->secret, 'specification' => ['options' => ['filters' => ['dataTypes' => ['tableData']]]]]);

        $ping = ['base' => ['id' => AIRTABLE_BASE], 'webhook' => ['id' => 'achWh1'], 'timestamp' => '2026-03-01T10:00:00.000Z'];
        test()->postJson('/api/hooks/apps/airtable?token='.$watch->secret, $ping)->assertOk();
        test()->postJson('/api/hooks/apps/airtable?token='.$watch->secret, $ping)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('does not register without a base id', function () {
        $trigger = airtableTrigger(nodeConfig: ['base_id' => '{{ trigger.base }}'], routes: ['POST /bases/'.AIRTABLE_BASE.'/webhooks' => ['id' => 'x']]);

        expect($trigger->subscription)->toBeNull()->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('refuses a wrong or missing token and is closed while the switch is off', function () {
        airtableTrigger(routes: ['POST /bases/'.AIRTABLE_BASE.'/webhooks' => ['id' => 'achWh1']]);
        $watch = ProviderWatch::query()->sole();
        $ping = ['base' => ['id' => AIRTABLE_BASE], 'webhook' => ['id' => 'achWh1'], 'timestamp' => 't'];

        test()->postJson('/api/hooks/apps/airtable?token=nope', $ping)->assertUnauthorized();
        test()->postJson('/api/hooks/apps/airtable', $ping)->assertUnauthorized();
        config(['triggers.push.airtable.enabled' => false]);
        test()->postJson('/api/hooks/apps/airtable?token='.$watch->secret, $ping)->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('says so when Airtable refuses the webhook, and drops the watch', function () {
        $trigger = airtableTrigger(routes: ['POST /bases/'.AIRTABLE_BASE.'/webhooks' => fn () => Http::response(['error' => ['type' => 'INVALID_PERMISSIONS_OR_MODEL_NOT_FOUND', 'message' => 'Invalid permissions']], 403)]);

        expect($trigger->subscription->status)->not->toBe(SubscriptionStatus::Active)->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('refreshes the webhook, makes it again when it is gone, and removes it with the watch', function () {
        airtableTrigger(routes: ['POST /bases/'.AIRTABLE_BASE.'/webhooks' => ['id' => 'achWh1']]);
        $watch = ProviderWatch::query()->sole();
        $source = app(AirtableSource::class);

        fakeAirtable(['POST /bases/'.AIRTABLE_BASE.'/webhooks/achWh1/refresh' => ['expirationTime' => '2026-03-15T00:00:00.000Z']]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        Http::assertSentCount(1);
        expect($watch->fresh()->external_id)->toBe('achWh1');

        fakeAirtable(['POST /bases/'.AIRTABLE_BASE.'/webhooks/achWh1/refresh' => fn () => Http::response(['error' => ['type' => 'NOT_FOUND']], 404), 'POST /bases/'.AIRTABLE_BASE.'/webhooks' => ['id' => 'achWh2'], 'DELETE /bases/'.AIRTABLE_BASE.'/webhooks/achWh1' => []]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('achWh2');

        fakeAirtable(['DELETE /bases/'.AIRTABLE_BASE.'/webhooks/achWh2' => []]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
