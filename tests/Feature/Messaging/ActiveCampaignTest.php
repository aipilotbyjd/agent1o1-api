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
use App\Nodes\Integrations\ActiveCampaign\AbstractActiveCampaignNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignAddNoteNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignAddTagNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignAddToAutomationNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignCreateDealNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignListContactsNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignListPipelinesNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignRemoveFromAutomationNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignRemoveTagNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignSubscribeToListNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignSyncContactNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignUpdateContactNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignUpdateDealNode;
use App\Services\Triggers\Push\ActiveCampaignSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ActiveCampaignOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const AC_URL = 'https://acme.api-us1.com';

/**
 * A run with a connected ActiveCampaign account in its workspace.
 */
function acRun(): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'activecampaign') ?? Connector::factory()->create(['key' => 'activecampaign']))->id,
        'data' => ['api_url' => AC_URL.'/', 'api_key' => 'ac_key'],
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer ActiveCampaign. A key is "METHOD /path" under /api/3 (a trailing * takes
 * any id); the value is the JSON body, a closure taking the request, or a ready
 * response. A route nobody answers is a 404, so a node that calls something
 * unexpected fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeActiveCampaign(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['*.api-us1.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/api/3~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['errors' => [['title' => "Nothing fakes {$key}"]]], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

/**
 * @return array<string, mixed>
 */
function acSent(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/api/3~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return (array) json_decode($found[0][0]->body(), true);
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function acContact(int $id = 1, array $extra = []): array
{
    return ['id' => (string) $id, 'email' => "c{$id}@example.com", 'firstName' => 'Ada', 'lastName' => 'Lovelace', 'phone' => '', 'cdate' => '2026-03-01T10:00:00-06:00', 'udate' => '2026-03-01T11:00:00-06:00', ...$extra];
}

describe('activecampaign nodes', function () {
    it('registers the nodes under the activecampaign connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'activecampaign_'));

        expect(count($types))->toBeGreaterThanOrEqual(29);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractActiveCampaignNode::class)->and($registry->resolve($type)->category())->toBe('activecampaign');
        }
    });

    it('only sends the key to ActiveCampaign\'s own domains', function () {
        expect(AbstractActiveCampaignNode::apiRoot('https://acme.api-us1.com/'))->toBe('https://acme.api-us1.com/api/3')
            ->and(AbstractActiveCampaignNode::apiRoot('https://acme.activehosted.com'))->toBe('https://acme.activehosted.com/api/3')
            ->and(fn () => AbstractActiveCampaignNode::apiRoot('https://acme.api-us1.com.evil.test'))->toThrow(InvalidArgumentException::class, 'account URL looks like')
            ->and(fn () => AbstractActiveCampaignNode::apiRoot('http://acme.api-us1.com'))->toThrow(InvalidArgumentException::class, 'account URL looks like')
            ->and(fn () => AbstractActiveCampaignNode::apiRoot('https://evil.test'))->toThrow(InvalidArgumentException::class, 'account URL looks like');
    });

    it('adds or updates a contact by email with custom fields, and reads its fields back by id', function () {
        fakeActiveCampaign(['POST /contact/sync' => ['contact' => acContact(5), 'fieldValues' => [['field' => '3', 'value' => 'Acme']]]]);

        $output = (new ActiveCampaignSyncContactNode)->execute(acRun(), ['email' => ' Ada@Example.com ', 'first_name' => 'Ada', 'custom_fields' => '{"3": "Acme"}'], []);

        expect($output)->toMatchArray(['id' => 5, 'email' => 'c5@example.com', 'first_name' => 'Ada', 'phone' => null, 'created_at' => '2026-03-01T16:00:00+00:00'])
            ->and((array) $output['custom_fields'])->toBe(['3' => 'Acme'])
            ->and(acSent('POST /contact/sync'))->toBe(['contact' => ['email' => 'ada@example.com', 'firstName' => 'Ada', 'fieldValues' => [['field' => '3', 'value' => 'Acme']]]]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Api-Token', 'ac_key') && str_starts_with($request->url(), 'https://acme.api-us1.com/api/3/'));
    });

    it('finds a contact by email once, takes an id as it is, and refuses an update that changes nothing', function () {
        fakeActiveCampaign(['GET /contacts' => ['contacts' => [acContact(9)]], 'PUT /contacts/9' => ['contact' => acContact(9, ['firstName' => 'Grace'])]]);
        $node = new ActiveCampaignUpdateContactNode;

        $node->execute(acRun(), ['contact' => 'Ada@Example.com', 'first_name' => 'Grace'], []);
        expect(acSent('PUT /contacts/9'))->toBe(['contact' => ['firstName' => 'Grace']]);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'email=ada%40example.com'));

        fakeActiveCampaign(['GET /contacts' => ['contacts' => []]]);
        expect(fn () => $node->execute(acRun(), ['contact' => 'ghost@example.com', 'first_name' => 'x'], []))->toThrow(InvalidArgumentException::class, 'no contact with the email')
            ->and(fn () => $node->execute(acRun(), ['contact' => '9'], []))->toThrow(InvalidArgumentException::class, 'Change at least one thing');
    });

    it('lists contacts by page with the filters ActiveCampaign understands', function () {
        $calls = 0;
        fakeActiveCampaign(['GET /contacts' => function (Request $request) use (&$calls) {
            $calls++;

            return ['contacts' => str_contains($request->url(), 'offset=0') ? array_map('acContact', range(1, 100)) : [acContact(101)]];
        }]);

        $output = (new ActiveCampaignListContactsNode)->execute(acRun(), ['list_id' => 4, 'tag_id' => 7, 'created_after' => '2026-03-01 00:00:00', 'limit' => 150], []);

        expect($calls)->toBe(2)->and($output['count'])->toBe(101);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'listid=4') && str_contains($request->url(), 'tagid=7') && str_contains(urldecode($request->url()), 'filters[created_after]=2026-03-01 00:00:00') && str_contains(urldecode($request->url()), 'orders[cdate]=DESC'));
    });

    it('subscribes to a list, tags a contact and takes the tag off through the contact-tag link', function () {
        fakeActiveCampaign([
            'POST /contactLists' => ['contactList' => ['id' => '1']],
            'POST /contactTags' => ['contactTag' => ['id' => '9']],
            'GET /contacts/9/contactTags' => ['contactTags' => [['id' => '31', 'tag' => '7'], ['id' => '32', 'tag' => '8']]],
            'DELETE /contactTags/31' => null,
        ]);
        $run = acRun();

        (new ActiveCampaignSubscribeToListNode)->execute($run, ['list_id' => 4, 'contact' => '9'], []);
        (new ActiveCampaignAddTagNode)->execute($run, ['tag_id' => 7, 'contact' => '9'], []);
        $removed = (new ActiveCampaignRemoveTagNode)->execute($run, ['tag_id' => 7, 'contact' => '9'], []);
        $missing = (new ActiveCampaignRemoveTagNode)->execute($run, ['tag_id' => 99, 'contact' => '9'], []);

        expect(acSent('POST /contactLists'))->toBe(['contactList' => ['list' => 4, 'contact' => 9, 'status' => 1]])
            ->and(acSent('POST /contactTags'))->toBe(['contactTag' => ['contact' => 9, 'tag' => 7]])
            ->and($removed)->toBe(['untagged' => true, 'tag_id' => 7, 'contact_id' => 9])
            ->and($missing['untagged'])->toBeFalse();
    });

    it('starts a contact on an automation and takes them out of it', function () {
        fakeActiveCampaign([
            'POST /contactAutomations' => ['contactAutomation' => ['id' => '77']],
            'GET /contactAutomations' => ['contactAutomations' => [['id' => '77'], ['id' => '78']]],
            'DELETE /contactAutomations/*' => null,
        ]);
        $run = acRun();

        $added = (new ActiveCampaignAddToAutomationNode)->execute($run, ['automation_id' => 3, 'contact' => '9'], []);
        $removed = (new ActiveCampaignRemoveFromAutomationNode)->execute($run, ['automation_id' => 3, 'contact' => '9'], []);

        expect($added)->toMatchArray(['added' => true, 'entry_id' => 77])
            ->and($removed['removed'])->toBeTrue();
        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'filters[contact]=9') && str_contains(urldecode($request->url()), 'filters[seriesid]=3'));
    });

    it('creates a deal with its value in cents, updates it, and marks it won', function () {
        fakeActiveCampaign([
            'POST /deals' => ['deal' => ['id' => '5', 'title' => 'Annual', 'value' => '490000', 'currency' => 'usd', 'group' => '1', 'stage' => '2', 'status' => '0', 'contact' => '9']],
            'PUT /deals/5' => ['deal' => ['id' => '5', 'status' => '1', 'value' => '500000']],
        ]);
        $run = acRun();

        $created = (new ActiveCampaignCreateDealNode)->execute($run, ['contact' => '9', 'title' => 'Annual', 'value' => '4900.00', 'currency' => 'USD', 'pipeline_id' => 1, 'stage_id' => 2], []);
        $updated = (new ActiveCampaignUpdateDealNode)->execute($run, ['deal_id' => 5, 'status' => 'won', 'value' => '5000'], []);

        expect($created)->toMatchArray(['id' => 5, 'value' => 4900, 'status' => 'open', 'pipeline_id' => 1, 'stage_id' => 2, 'contact_id' => 9])
            ->and(acSent('POST /deals'))->toBe(['deal' => ['contact' => 9, 'title' => 'Annual', 'value' => 490000, 'currency' => 'usd', 'group' => 1, 'stage' => 2, 'status' => 0]])
            ->and($updated['status'])->toBe('won')
            ->and(acSent('PUT /deals/5'))->toBe(['deal' => ['value' => 500000, 'status' => 1]])
            ->and(fn () => (new ActiveCampaignCreateDealNode)->execute($run, ['contact' => '9', 'title' => 'x', 'value' => 'lots', 'pipeline_id' => 1, 'stage_id' => 2], []))->toThrow(InvalidArgumentException::class, 'must be a number');
    });

    it('lists pipelines with their stages grouped', function () {
        fakeActiveCampaign([
            'GET /dealGroups' => ['dealGroups' => [['id' => '1', 'title' => 'Sales', 'currency' => 'usd']]],
            'GET /dealStages' => ['dealStages' => [['id' => '2', 'title' => 'Qualified', 'group' => '1', 'order' => '1'], ['id' => '3', 'title' => 'Other', 'group' => '9', 'order' => '1']]],
        ]);

        $output = (new ActiveCampaignListPipelinesNode)->execute(acRun(), [], []);

        expect($output['pipelines'])->toBe([['id' => 1, 'name' => 'Sales', 'currency' => 'usd', 'stages' => [['id' => 2, 'name' => 'Qualified', 'order' => 1]]]]);
    });

    it('adds a note to a contact or to a deal', function () {
        fakeActiveCampaign(['POST /notes' => ['note' => ['id' => '3', 'note' => 'Called', 'cdate' => '2026-03-01T10:00:00-06:00']]]);
        $run = acRun();

        (new ActiveCampaignAddNoteNode)->execute($run, ['note' => 'Called', 'contact' => '9'], []);
        expect(acSent('POST /notes'))->toBe(['note' => ['note' => 'Called', 'relid' => 9, 'reltype' => 'Subscriber']]);

        fakeActiveCampaign(['POST /notes' => ['note' => ['id' => '4']]]);
        (new ActiveCampaignAddNoteNode)->execute($run, ['note' => 'Won', 'on' => 'deal', 'deal_id' => 5], []);
        expect(acSent('POST /notes')['note'])->toBe(['note' => 'Won', 'relid' => 5, 'reltype' => 'Deal']);
    });

    it('says what ActiveCampaign said when it refuses', function () {
        fakeActiveCampaign(['POST /contact/sync' => fn () => Http::response(['errors' => [['title' => 'Email address is invalid.']]], 422)]);

        expect(fn () => (new ActiveCampaignSyncContactNode)->execute(acRun(), ['email' => 'a@b.co'], []))->toThrow(RuntimeException::class, 'ActiveCampaign error: Email address is invalid.');
    });
});

describe('activecampaign dropdowns', function () {
    it('lists lists and a pipeline\'s stages, and needs a pipeline for stages', function () {
        fakeActiveCampaign(['GET /lists' => ['lists' => [['id' => '4', 'name' => 'Newsletter']]], 'GET /dealStages' => ['dealStages' => [['id' => '2', 'title' => 'Qualified']]]]);
        $query = fn (array $config = []): NodeOptionsQuery => new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), $config, 'ac_key', null, null, ['api_url' => AC_URL]);
        $source = app(ActiveCampaignOptions::class);

        expect($source->load('activecampaign.lists', $query())->toArray())->toBe(['options' => [['value' => '4', 'label' => 'Newsletter']], 'next_cursor' => null])
            ->and($source->load('activecampaign.stages', $query(['pipeline_id' => 1]))->toArray()['options'])->toBe([['value' => '2', 'label' => 'Qualified']])
            ->and(fn () => $source->load('activecampaign.stages', $query()))->toThrow(App\Exceptions\ConnectorException::class, 'Choose a pipeline first')
            ->and(fn () => $source->load('activecampaign.lists', new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'B']), User::factory()->create(), [], 'k', null, null, ['api_url' => 'https://evil.test'])))->toThrow(App\Exceptions\ConnectorException::class, 'account URL looks like');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Api-Token', 'ac_key') && str_contains(urldecode($request->url()), 'filters[d_groupid]=1'));
    });
});

/**
 * A published workflow whose first step is an ActiveCampaign reader switched on as
 * a trigger, with its subscription synced against a faked ActiveCampaign.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 */
function acTrigger(string $nodeType = 'activecampaign_list_contacts', array $nodeConfig = [], array $routes = [], bool $sync = true, string $apiUrl = AC_URL): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeActiveCampaign($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'activecampaign') ?? Connector::factory()->create(['key' => 'activecampaign']))
        ->create(['data' => ['api_url' => $apiUrl, 'api_key' => 'ac_key'], 'created_by' => $owner->id]);

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

describe('activecampaign triggers', function () {
    it('polls new contacts: a baseline first, then one run for each', function () {
        $trigger = acTrigger(sync: false);
        $poll = fn (array $ids) => fakeActiveCampaign(['GET /contacts' => ['contacts' => array_map('acContact', $ids)]]);

        $poll([1]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([3, 2, 1]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe([2, 3]);
    });

    it('makes every edit of a contact its own event', function () {
        $node = app(NodeRegistry::class)->resolve('activecampaign_list_updated_contacts');

        expect($node->triggerItemId(['id' => 1, 'updated_at' => 'a']))->not->toBe($node->triggerItemId(['id' => 1, 'updated_at' => 'b']));
    });

    it('registers one webhook per set of events with a token URL, and starts a poll when a form arrives', function () {
        $trigger = acTrigger(routes: ['POST /webhooks' => ['webhook' => ['id' => '12']]]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('events:subscribe')
            ->and($watch->external_id)->toBe('12')
            ->and($trigger->subscription->routing_key)->toBe(hash('sha256', $watch->secret))
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(acSent('POST /webhooks'))->toBe(['webhook' => ['name' => 'Flows', 'url' => url('/api/hooks/apps/activecampaign').'?token='.$watch->secret, 'events' => ['subscribe'], 'sources' => ['public', 'system', 'admin', 'api']]]);

        $form = ['type' => 'subscribe', 'date_time' => '2026-03-01 10:00:00', 'contact' => ['id' => '1', 'email' => 'a@x.co']];
        test()->call('POST', '/api/hooks/apps/activecampaign?token='.$watch->secret, $form)->assertOk();
        test()->call('POST', '/api/hooks/apps/activecampaign?token='.$watch->secret, $form)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('listens for the right events per trigger', function (string $type, string $key, array $events) {
        acTrigger($type, [], ['POST /webhooks' => ['webhook' => ['id' => '1']]]);

        expect(ProviderWatch::query()->sole()->resource_key)->toBe($key)
            ->and(acSent('POST /webhooks')['webhook']['events'])->toBe($events);
    })->with([
        'contacts' => ['activecampaign_list_contacts', 'events:subscribe', ['subscribe']],
        'updates' => ['activecampaign_list_updated_contacts', 'events:subscribe,update', ['subscribe', 'update']],
        'deals' => ['activecampaign_list_deals', 'events:deal_add', ['deal_add']],
    ]);

    it('refuses a wrong or missing token and is closed while the switch is off', function () {
        acTrigger(routes: ['POST /webhooks' => ['webhook' => ['id' => '12']]]);
        $watch = ProviderWatch::query()->sole();

        test()->call('POST', '/api/hooks/apps/activecampaign?token=nope', ['type' => 'subscribe'])->assertUnauthorized();
        test()->call('POST', '/api/hooks/apps/activecampaign', ['type' => 'subscribe'])->assertUnauthorized();
        config(['triggers.push.activecampaign.enabled' => false]);
        test()->call('POST', '/api/hooks/apps/activecampaign?token='.$watch->secret, ['type' => 'subscribe'])->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('says so when ActiveCampaign refuses the webhook or the account address is wrong', function () {
        $refused = acTrigger(routes: ['POST /webhooks' => fn () => Http::response(['errors' => [['title' => 'Invalid event']]], 422)]);
        expect($refused->subscription->status)->toBe(SubscriptionStatus::Failed)->and($refused->subscription->last_error)->toContain('422')->and(ProviderWatch::query()->count())->toBe(0);

        $wrongHost = acTrigger(apiUrl: 'https://evil.test');
        expect($wrongHost->subscription->status)->toBe(SubscriptionStatus::ReconnectNeeded)->and($wrongHost->subscription->last_error)->toContain('account URL looks like');
    });

    it('makes the webhook again if it was deleted, and removes it with the watch', function () {
        acTrigger(routes: ['POST /webhooks' => ['webhook' => ['id' => '12']]]);
        $watch = ProviderWatch::query()->sole();
        $source = app(ActiveCampaignSource::class);

        fakeActiveCampaign(['GET /webhooks/12' => fn () => Http::response(['errors' => []], 404), 'POST /webhooks' => ['webhook' => ['id' => '13']]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('13')->and($watch->fresh()->status)->toBe(SubscriptionStatus::Active);

        fakeActiveCampaign(['DELETE /webhooks/13' => null]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
