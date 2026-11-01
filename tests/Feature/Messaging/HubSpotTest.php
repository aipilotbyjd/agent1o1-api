<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\HubSpot\AbstractHubSpotNode;
use App\Nodes\Integrations\HubSpot\HubSpotAddNoteNode;
use App\Nodes\Integrations\HubSpot\HubSpotAddToListNode;
use App\Nodes\Integrations\HubSpot\HubSpotAssociateNode;
use App\Nodes\Integrations\HubSpot\HubSpotCreateContactNode;
use App\Nodes\Integrations\HubSpot\HubSpotCreateTaskNode;
use App\Nodes\Integrations\HubSpot\HubSpotDeleteDealNode;
use App\Nodes\Integrations\HubSpot\HubSpotGetContactNode;
use App\Nodes\Integrations\HubSpot\HubSpotGetDealNode;
use App\Nodes\Integrations\HubSpot\HubSpotListChangedDealsNode;
use App\Nodes\Integrations\HubSpot\HubSpotListOwnersNode;
use App\Nodes\Integrations\HubSpot\HubSpotSearchContactsNode;
use App\Nodes\Integrations\HubSpot\HubSpotUpdateCompanyNode;
use App\Nodes\Integrations\HubSpot\HubSpotUpsertContactNode;
use App\Services\Triggers\Push\PushSources;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\HubSpotOptions;
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
function hubspotConfig(array $config = []): array
{
    return ['access_token' => 'pat-hub', ...$config];
}

/**
 * Answer HubSpot. A key is "METHOD /path" (a trailing * takes any id); the value is
 * the JSON body, or a closure returning a response. A route nobody answers is a
 * 404, so a node that calls something unexpected fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeHubSpot(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.hubapi.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.urldecode((string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['status' => 'error', 'message' => "Nothing fakes {$key}", 'category' => 'OBJECT_NOT_FOUND'], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : Http::response($answer ?? []);
    }]);
}

/**
 * The request made to a route.
 */
function hubspotRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.urldecode((string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function hubspotBody(string $route): array
{
    return (array) json_decode(hubspotRequest($route)->body(), true);
}

/**
 * @param  array<string, mixed>  $properties
 * @return array<string, mixed>
 */
function hubspotRecord(string $id = '51', array $properties = ['email' => 'ada@example.com', 'firstname' => 'Ada'], array $extra = []): array
{
    return ['id' => $id, 'properties' => $properties, 'createdAt' => '2026-03-01T10:00:00.000Z', 'updatedAt' => '2026-03-01T11:00:00.000Z', 'archived' => false, ...$extra];
}

describe('hubspot nodes', function () {
    it('registers the nodes under the hubspot connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'hubspot_'));

        expect(count($types))->toBeGreaterThanOrEqual(38);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractHubSpotNode::class)->and($registry->resolve($type)->category())->toBe('hubspot');
        }
    });

    it('creates a contact from its fields and the JSON map, with a bearer token', function () {
        fakeHubSpot(['POST /crm/v3/objects/contacts' => hubspotRecord()]);

        $output = (new HubSpotCreateContactNode)->execute(Run::factory()->create(), hubspotConfig(['email' => 'ada@example.com', 'first_name' => 'Ada', 'lifecycle_stage' => 'lead', 'owner_id' => '99', 'properties' => '{"city": "Berlin", "newsletter": true}']), []);

        expect($output)->toMatchArray(['id' => '51', 'created_at' => '2026-03-01T10:00:00.000Z', 'archived' => false])
            ->and((array) $output['properties'])->toBe(['email' => 'ada@example.com', 'firstname' => 'Ada'])
            ->and(hubspotBody('POST /crm/v3/objects/contacts'))->toBe(['properties' => ['city' => 'Berlin', 'newsletter' => 'true', 'email' => 'ada@example.com', 'firstname' => 'Ada', 'lifecyclestage' => 'lead', 'hubspot_owner_id' => '99']]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer pat-hub'));
    });

    it('checks properties and ids before calling HubSpot', function () {
        fakeHubSpot([]);
        $run = Run::factory()->create();

        expect(fn () => (new HubSpotCreateContactNode)->execute($run, hubspotConfig(), []))->toThrow(InvalidArgumentException::class, 'at least one property')
            ->and(fn () => (new HubSpotCreateContactNode)->execute($run, hubspotConfig(['properties' => '{"a b": 1}']), []))->toThrow(InvalidArgumentException::class, 'not a HubSpot property name')
            ->and(fn () => (new HubSpotCreateContactNode)->execute($run, hubspotConfig(['properties' => '["x"]']), []))->toThrow(InvalidArgumentException::class, 'JSON object')
            ->and(fn () => (new HubSpotUpdateCompanyNode)->execute($run, hubspotConfig(['record_id' => '12/../x', 'name' => 'Acme']), []))->toThrow(InvalidArgumentException::class, 'must be a HubSpot id')
            ->and(fn () => (new HubSpotUpdateCompanyNode)->execute($run, hubspotConfig(['record_id' => '12']), []))->toThrow(InvalidArgumentException::class, 'Change at least one property')
            ->and(fn () => (new HubSpotDeleteDealNode)->execute($run, hubspotConfig(), []))->toThrow(InvalidArgumentException::class, 'The deal is required');
        Http::assertNothingSent();
    });

    it('updates a company and deletes a deal by id', function () {
        fakeHubSpot(['PATCH /crm/v3/objects/companies/12' => hubspotRecord('12', ['name' => 'Acme Inc.']), 'DELETE /crm/v3/objects/deals/7' => null]);
        $run = Run::factory()->create();

        $updated = (new HubSpotUpdateCompanyNode)->execute($run, hubspotConfig(['record_id' => '12', 'name' => 'Acme Inc.', 'domain' => 'acme.com']), []);
        $deleted = (new HubSpotDeleteDealNode)->execute($run, hubspotConfig(['record_id' => '7']), []);

        expect((array) $updated['properties'])->toBe(['name' => 'Acme Inc.'])
            ->and(hubspotBody('PATCH /crm/v3/objects/companies/12'))->toBe(['properties' => ['name' => 'Acme Inc.', 'domain' => 'acme.com']])
            ->and($deleted)->toBe(['deleted' => true, 'id' => '7']);
    });

    it('gets a contact by email through idProperty, by id otherwise, and says when it is not there', function () {
        fakeHubSpot(['GET /crm/v3/objects/contacts/ada@example.com' => hubspotRecord(), 'GET /crm/v3/objects/deals/9' => fn () => Http::response(['status' => 'error', 'message' => 'resource not found', 'category' => 'OBJECT_NOT_FOUND'], 404)]);
        $run = Run::factory()->create();

        $found = (new HubSpotGetContactNode)->execute($run, hubspotConfig(['record_id' => 'ada@example.com']), []);

        expect($found)->toMatchArray(['found' => true, 'id' => '51'])
            ->and((new HubSpotGetDealNode)->execute($run, hubspotConfig(['record_id' => '9']), []))->toBe(['found' => false, 'id' => '9']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'idProperty=email') && str_contains($request->url(), 'properties=firstname'));
    });

    it('searches with one condition, newest first, following the cursor', function () {
        fakeHubSpot(['POST /crm/v3/objects/contacts/search' => fn (Request $request) => ($request['after'] ?? null) === 'cur1'
            ? ['results' => [hubspotRecord('53')]]
            : ['results' => [hubspotRecord('51'), hubspotRecord('52')], 'paging' => ['next' => ['after' => 'cur1']]]]);

        $output = (new HubSpotSearchContactsNode)->execute(Run::factory()->create(), hubspotConfig(['property' => 'email', 'operator' => 'CONTAINS_TOKEN', 'value' => 'example.com', 'limit' => 150, 'read_properties' => ['hs_analytics_source']]), []);
        $first = json_decode(Http::recorded()->all()[0][0]->body(), true);

        expect(array_column($output['contacts'], 'id'))->toBe(['51', '52', '53'])
            ->and($first['filterGroups'])->toBe([['filters' => [['propertyName' => 'email', 'operator' => 'CONTAINS_TOKEN', 'value' => 'example.com']]]])
            ->and($first['sorts'])->toBe([['propertyName' => 'createdate', 'direction' => 'DESCENDING']])
            ->and($first['properties'])->toContain('email', 'hs_analytics_source')
            ->and($first['limit'])->toBe(100);
        Http::assertSentCount(2);
    });

    it('takes several conditions as JSON, and refuses anything that is not a property or an operator', function () {
        fakeHubSpot(['POST /crm/v3/objects/contacts/search' => ['results' => []]]);
        $node = new HubSpotSearchContactsNode;
        $run = Run::factory()->create();

        $node->execute($run, hubspotConfig(['filters' => '[{"propertyName": "lifecyclestage", "operator": "EQ", "value": "lead"}, {"propertyName": "phone", "operator": "HAS_PROPERTY"}]']), []);

        expect(hubspotBody('POST /crm/v3/objects/contacts/search')['filterGroups'][0]['filters'])->toBe([['propertyName' => 'lifecyclestage', 'operator' => 'EQ', 'value' => 'lead'], ['propertyName' => 'phone', 'operator' => 'HAS_PROPERTY']])
            ->and(fn () => $node->execute($run, hubspotConfig(['filters' => '[{"propertyName": "x", "operator": "DROP", "value": "1"}]']), []))->toThrow(InvalidArgumentException::class, 'not a search operator')
            ->and(fn () => $node->execute($run, hubspotConfig(['property' => 'email) OR (1', 'value' => 'a']), []))->toThrow(InvalidArgumentException::class, 'not a HubSpot property name');
    });

    it('creates or updates a contact by email, and says which', function () {
        fakeHubSpot(['POST /crm/v3/objects/contacts/batch/upsert' => ['results' => [hubspotRecord('51', ['email' => 'ada@example.com'], ['new' => true])]]]);
        $node = new HubSpotUpsertContactNode;

        $output = $node->execute(Run::factory()->create(), hubspotConfig(['email' => ' Ada@Example.com ', 'first_name' => 'Ada']), []);

        expect($output['created'])->toBeTrue()
            ->and(hubspotBody('POST /crm/v3/objects/contacts/batch/upsert'))->toBe(['inputs' => [['id' => 'ada@example.com', 'idProperty' => 'email', 'properties' => ['email' => 'ada@example.com', 'firstname' => 'Ada']]]])
            ->and(fn () => $node->execute(Run::factory()->create(), hubspotConfig(['email' => 'nope']), []))->toThrow(InvalidArgumentException::class, 'is not an email address');
    });

    it('logs a note and a task on a record with the right association', function () {
        fakeHubSpot(['POST /crm/v3/objects/notes' => ['id' => '700'], 'POST /crm/v3/objects/tasks' => ['id' => '701']]);
        $run = Run::factory()->create();

        $note = (new HubSpotAddNoteNode)->execute($run, hubspotConfig(['object' => 'deals', 'record_id' => '7', 'text' => 'Sent the quote']), []);
        $task = (new HubSpotCreateTaskNode)->execute($run, hubspotConfig(['object' => 'contacts', 'record_id' => '51', 'subject' => 'Call back', 'priority' => 'HIGH', 'owner_id' => '99', 'due' => '2026-03-05T09:00:00Z']), []);

        expect($note)->toBe(['id' => '700', 'object' => 'deals', 'record_id' => '7'])
            ->and($task)->toBe(['id' => '701', 'object' => 'contacts', 'record_id' => '51'])
            ->and(hubspotBody('POST /crm/v3/objects/notes')['associations'])->toBe([['to' => ['id' => '7'], 'types' => [['associationCategory' => 'HUBSPOT_DEFINED', 'associationTypeId' => 214]]]])
            ->and(hubspotBody('POST /crm/v3/objects/notes')['properties']['hs_note_body'])->toBe('Sent the quote')
            ->and(hubspotBody('POST /crm/v3/objects/tasks')['properties'])->toBe(['hs_task_subject' => 'Call back', 'hs_timestamp' => '2026-03-05T09:00:00.000Z', 'hs_task_status' => 'NOT_STARTED', 'hs_task_priority' => 'HIGH', 'hubspot_owner_id' => '99'])
            ->and(hubspotBody('POST /crm/v3/objects/tasks')['associations'][0]['types'][0]['associationTypeId'])->toBe(204)
            ->and(fn () => (new HubSpotAddNoteNode)->execute($run, hubspotConfig(['object' => 'leads', 'record_id' => '7', 'text' => 'x']), []))->toThrow(InvalidArgumentException::class, 'not a HubSpot record type');
    });

    it('links two records, lists owners across pages, and changes a static list in batches', function () {
        fakeHubSpot([
            'PUT /crm/v4/objects/contacts/51/associations/default/companies/12' => [],
            'GET /crm/v3/owners' => fn (Request $request) => str_contains($request->url(), 'after=o2') ? ['results' => [['id' => '2', 'email' => 'bob@acme.com', 'firstName' => 'Bob', 'lastName' => 'Smith']]] : ['results' => [['id' => '1', 'email' => 'ada@acme.com', 'firstName' => 'Ada', 'lastName' => '']], 'paging' => ['next' => ['after' => 'o2']]],
            'PUT /crm/v3/lists/44/memberships/add' => ['recordsIdsAdded' => []],
        ]);
        $run = Run::factory()->create();

        $linked = (new HubSpotAssociateNode)->execute($run, hubspotConfig(['from_object' => 'contacts', 'from_id' => '51', 'to_object' => 'companies', 'to_id' => '12']), []);
        $owners = (new HubSpotListOwnersNode)->execute($run, hubspotConfig(), []);
        $listed = (new HubSpotAddToListNode)->execute($run, hubspotConfig(['list_id' => '44', 'contact_ids' => array_map('strval', range(1, 150))]), []);

        expect($linked)->toBe(['linked' => true, 'from' => 'contacts/51', 'to' => 'companies/12'])
            ->and(array_column($owners['owners'], 'name'))->toBe(['Ada', 'Bob Smith'])
            ->and($listed)->toBe(['added' => 150, 'list_id' => '44']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/memberships/add') && count($request->data()) === 100);
        expect(fn () => (new HubSpotAddToListNode)->execute($run, hubspotConfig(['list_id' => '44', 'contact_ids' => ['a1']]), []))->toThrow(InvalidArgumentException::class, 'must be a HubSpot id');
    });

    it('says what HubSpot said when it refuses', function () {
        fakeHubSpot(['POST /crm/v3/objects/contacts' => fn () => Http::response(['status' => 'error', 'message' => 'Property values were not valid', 'category' => 'VALIDATION_ERROR'], 400)]);

        expect(fn () => (new HubSpotCreateContactNode)->execute(Run::factory()->create(), hubspotConfig(['email' => 'a@b.co']), []))->toThrow(RuntimeException::class, 'HubSpot error: Property values were not valid (VALIDATION_ERROR).');

        fakeHubSpot(['POST /crm/v3/objects/contacts' => fn () => Http::response(['message' => 'missing scopes'], 403)]);

        expect(fn () => (new HubSpotCreateContactNode)->execute(Run::factory()->create(), hubspotConfig(['email' => 'a@b.co']), []))->toThrow(RuntimeException::class, 'scope this needs');
    });
});

describe('hubspot dropdowns', function () {
    it('lists owners, pipelines, and stages of the chosen pipeline or of all of them', function () {
        fakeHubSpot([
            'GET /crm/v3/owners' => ['results' => [['id' => '1', 'email' => 'ada@acme.com', 'firstName' => 'Ada', 'lastName' => 'Lovelace']], 'paging' => ['next' => ['after' => 'o2']]],
            'GET /crm/v3/pipelines/deals' => ['results' => [
                ['id' => 'default', 'label' => 'Sales', 'stages' => [['id' => 's1', 'label' => 'Qualified'], ['id' => 's2', 'label' => 'Won']]],
                ['id' => 'p2', 'label' => 'Renewals', 'stages' => [['id' => 'r1', 'label' => 'Upcoming']]],
            ]],
        ]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = fn (array $config = []): NodeOptionsQuery => new NodeOptionsQuery($workspace, User::factory()->create(), $config, 'pat-hub');
        $source = app(HubSpotOptions::class);

        expect($source->load('hubspot.owners', $query())->toArray())->toBe(['options' => [['value' => '1', 'label' => 'Ada Lovelace', 'description' => 'ada@acme.com']], 'next_cursor' => 'o2'])
            ->and(array_column($source->load('hubspot.deal_pipelines', $query())->toArray()['options'], 'label'))->toBe(['Sales', 'Renewals'])
            ->and(array_column($source->load('hubspot.deal_stages', $query(['pipeline_id' => 'default']))->toArray()['options'], 'label'))->toBe(['Qualified', 'Won'])
            ->and(array_column($source->load('hubspot.deal_stages', $query())->toArray()['options'], 'label'))->toBe(['Sales: Qualified', 'Sales: Won', 'Renewals: Upcoming']);
    });
});

describe('hubspot triggers', function () {
    it('polls new contacts by creation time: a baseline first, then one run for each', function () {
        Queue::fake([FireTriggerEvent::class]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'hubspot') ?? Connector::factory()->create(['key' => 'hubspot']))
            ->create(['data' => ['api_key' => 'pat-hub'], 'created_by' => $owner->id]);

        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'hubspot_list_new_contacts', 'config' => ['credential_id' => $credential->id, 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);

        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        $poll = fn (array $ids) => fakeHubSpot(['POST /crm/v3/objects/contacts/search' => ['results' => array_map(fn (string $id): array => hubspotRecord($id), $ids)]]);

        $poll(['1']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['3', '2', '1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['2', '3'])
            ->and(hubspotBody('POST /crm/v3/objects/contacts/search')['filterGroups'][0]['filters'][0])->toMatchArray(['propertyName' => 'createdate', 'operator' => 'GTE']);
    });

    it('filters changed deals by their modified property and makes every change its own event', function () {
        fakeHubSpot(['POST /crm/v3/objects/deals/search' => ['results' => [hubspotRecord('7', ['dealname' => 'Acme', 'hs_lastmodifieddate' => '2026-03-01T11:00:00Z'])]]]);
        $node = new HubSpotListChangedDealsNode;

        $node->execute(Run::factory()->create(), hubspotConfig(), []);

        expect(hubspotBody('POST /crm/v3/objects/deals/search')['filterGroups'][0]['filters'][0]['propertyName'])->toBe('hs_lastmodifieddate')
            ->and($node->triggerItemId(['id' => '7', 'properties' => ['hs_lastmodifieddate' => 'a']]))->not->toBe($node->triggerItemId(['id' => '7', 'properties' => ['hs_lastmodifieddate' => 'b']]))
            ->and(app(PushSources::class)->source('hubspot'))->toBeNull();
    });
});
