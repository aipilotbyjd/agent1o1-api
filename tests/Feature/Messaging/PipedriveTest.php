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
use App\Nodes\Integrations\Pipedrive\AbstractPipedriveNode;
use App\Nodes\Integrations\Pipedrive\PipedriveAddNoteNode;
use App\Nodes\Integrations\Pipedrive\PipedriveCreateActivityNode;
use App\Nodes\Integrations\Pipedrive\PipedriveCreateDealNode;
use App\Nodes\Integrations\Pipedrive\PipedriveCreateLeadNode;
use App\Nodes\Integrations\Pipedrive\PipedriveCreatePersonNode;
use App\Nodes\Integrations\Pipedrive\PipedriveDeleteDealNode;
use App\Nodes\Integrations\Pipedrive\PipedriveGetDealNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListChangedDealsNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListDealsNode;
use App\Nodes\Integrations\Pipedrive\PipedriveMarkDealNode;
use App\Nodes\Integrations\Pipedrive\PipedriveSearchPersonsNode;
use App\Nodes\Integrations\Pipedrive\PipedriveUpdateDealNode;
use App\Nodes\Integrations\Pipedrive\PipedriveUpdateLeadNode;
use App\Services\Triggers\Push\PipedriveSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\PipedriveOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const PIPEDRIVE_LEAD = '4b2d7c0e-1f2a-4c3b-9d4e-5f6a7b8c9d0e';

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function pipedriveConfig(array $config = []): array
{
    return ['access_token' => 'pd_token', ...$config];
}

/**
 * Answer Pipedrive. A key is "METHOD /path" under /v1 (a trailing * takes any id);
 * the value is the JSON body, or a closure returning a response. A route nobody
 * answers is a 404, so a node that calls something unexpected fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakePipedrive(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.pipedrive.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/v1~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['success' => false, 'error' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : Http::response($answer ?? ['success' => true]);
    }]);
}

/**
 * The request made to a route.
 */
function pipedriveRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/v1~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function pipedriveBody(string $route): array
{
    return (array) json_decode(pipedriveRequest($route)->body(), true);
}

/**
 * @return array<string, string>
 */
function pipedriveQuery(string $route): array
{
    parse_str((string) parse_url(pipedriveRequest($route)->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function pipedriveDeal(int $id = 7, array $extra = []): array
{
    return ['id' => $id, 'title' => 'Acme annual plan', 'value' => 4800, 'currency' => 'EUR', 'status' => 'open', 'stage_id' => 3, 'person_id' => ['name' => 'Ada', 'value' => 51], 'org_id' => ['name' => 'Acme', 'value' => 12], 'add_time' => now()->subMinutes(10)->utc()->format('Y-m-d H:i:s'), 'update_time' => now()->subMinutes(5)->utc()->format('Y-m-d H:i:s'), ...$extra];
}

/**
 * @param  mixed  $data
 * @return array<string, mixed>
 */
function pipedriveOk(mixed $data, bool $more = false, int $next = 0): array
{
    return ['success' => true, 'data' => $data, 'additional_data' => ['pagination' => ['more_items_in_collection' => $more, 'next_start' => $next]]];
}

describe('pipedrive nodes', function () {
    it('registers the nodes under the pipedrive connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'pipedrive_'));

        expect(count($types))->toBeGreaterThanOrEqual(43);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractPipedriveNode::class)->and($registry->resolve($type)->category())->toBe('pipedrive');
        }
    });

    it('creates a deal with the token in a header, numbers as numbers and custom fields beside the standard ones', function () {
        fakePipedrive(['POST /deals' => pipedriveOk(pipedriveDeal())]);

        $output = (new PipedriveCreateDealNode)->execute(Run::factory()->create(), pipedriveConfig(['title' => ' Acme annual plan ', 'value' => '4800', 'currency' => 'eur', 'person_id' => '51', 'org_id' => '12', 'stage_id' => '3', 'expected_close_date' => '2026-03-31T10:00:00Z', 'owner_id' => '99', 'custom_fields' => '{"a1b2c3": "Gold"}']), []);

        expect($output['id'])->toBe(7)
            ->and((array) $output['fields'])->toMatchArray(['title' => 'Acme annual plan', 'person_id' => 'Ada', 'person_id_value' => 51, 'org_id' => 'Acme', 'status' => 'open'])
            ->and(pipedriveBody('POST /deals'))->toBe(['title' => 'Acme annual plan', 'value' => 4800, 'currency' => 'EUR', 'person_id' => 51, 'org_id' => 12, 'stage_id' => 3, 'expected_close_date' => '2026-03-31', 'user_id' => 99, 'a1b2c3' => 'Gold']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-token', 'pd_token'));
    });

    it('checks ids, numbers, emails and field keys before calling Pipedrive', function () {
        fakePipedrive([]);
        $run = Run::factory()->create();

        expect(fn () => (new PipedriveCreateDealNode)->execute($run, pipedriveConfig(['title' => 'x', 'value' => 'lots']), []))->toThrow(InvalidArgumentException::class, 'must be a number')
            ->and(fn () => (new PipedriveCreateDealNode)->execute($run, pipedriveConfig(['title' => 'x', 'person_id' => '5/../x']), []))->toThrow(InvalidArgumentException::class, 'must be a Pipedrive id')
            ->and(fn () => (new PipedriveCreateDealNode)->execute($run, pipedriveConfig(['title' => 'x', 'custom_fields' => '{"a b": 1}']), []))->toThrow(InvalidArgumentException::class, 'is not a Pipedrive field key')
            ->and(fn () => (new PipedriveCreatePersonNode)->execute($run, pipedriveConfig(['name' => 'Ada', 'emails' => 'nope']), []))->toThrow(InvalidArgumentException::class, 'is not an email address')
            ->and(fn () => (new PipedriveUpdateDealNode)->execute($run, pipedriveConfig(['record_id' => '7']), []))->toThrow(InvalidArgumentException::class, 'Change at least one thing')
            ->and(fn () => (new PipedriveUpdateLeadNode)->execute($run, pipedriveConfig(['record_id' => '7', 'title' => 'x']), []))->toThrow(InvalidArgumentException::class, 'lead id (a UUID)')
            ->and(fn () => (new PipedriveCreateActivityNode)->execute($run, pipedriveConfig(['subject' => 'x', 'due_time' => '2pm']), []))->toThrow(InvalidArgumentException::class, 'HH:MM');
        Http::assertNothingSent();
    });

    it('sends a person\'s emails and phones as entries with the first marked main', function () {
        fakePipedrive(['POST /persons' => pipedriveOk(['id' => 51, 'name' => 'Ada Lovelace'])]);

        (new PipedriveCreatePersonNode)->execute(Run::factory()->create(), pipedriveConfig(['name' => 'Ada Lovelace', 'emails' => 'Ada@Example.com, ada@work.co', 'phones' => ['+14155550123'], 'org_id' => '12']), []);

        expect(pipedriveBody('POST /persons'))->toBe(['name' => 'Ada Lovelace', 'email' => [['value' => 'ada@example.com', 'primary' => true], ['value' => 'ada@work.co', 'primary' => false]], 'phone' => [['value' => '+14155550123', 'primary' => true]], 'org_id' => 12]);
    });

    it('updates a deal by PUT and a lead by PATCH with its UUID, and creates a lead with a value object', function () {
        fakePipedrive(['PUT /deals/7' => pipedriveOk(pipedriveDeal()), 'PATCH /leads/'.PIPEDRIVE_LEAD => pipedriveOk(['id' => PIPEDRIVE_LEAD, 'title' => 'New title']), 'POST /leads' => pipedriveOk(['id' => PIPEDRIVE_LEAD, 'title' => 'Enquiry'])]);
        $run = Run::factory()->create();

        (new PipedriveUpdateDealNode)->execute($run, pipedriveConfig(['record_id' => '7', 'stage_id' => '4']), []);
        (new PipedriveUpdateLeadNode)->execute($run, pipedriveConfig(['record_id' => PIPEDRIVE_LEAD, 'title' => 'New title']), []);
        (new PipedriveCreateLeadNode)->execute($run, pipedriveConfig(['title' => 'Enquiry', 'person_id' => '51', 'value' => '900', 'currency' => 'eur']), []);

        expect(pipedriveBody('PUT /deals/7'))->toBe(['stage_id' => 4])
            ->and(pipedriveBody('PATCH /leads/'.PIPEDRIVE_LEAD))->toBe(['title' => 'New title'])
            ->and(pipedriveBody('POST /leads'))->toBe(['title' => 'Enquiry', 'person_id' => 51, 'value' => ['amount' => 900, 'currency' => 'EUR']]);
    });

    it('reads a deal and says when it is not there, then deletes and closes deals', function () {
        fakePipedrive(['GET /deals/7' => pipedriveOk(pipedriveDeal()), 'GET /deals/8' => fn () => Http::response(['success' => false, 'error' => 'Deal not found'], 404), 'DELETE /deals/7' => ['success' => true, 'data' => ['id' => 7]], 'PUT /deals/7' => pipedriveOk(pipedriveDeal(7, ['status' => 'lost', 'lost_time' => '2026-03-01 10:00:00']))]);
        $run = Run::factory()->create();

        expect((new PipedriveGetDealNode)->execute($run, pipedriveConfig(['record_id' => '7']), []))->toMatchArray(['found' => true, 'id' => 7])
            ->and((new PipedriveGetDealNode)->execute($run, pipedriveConfig(['record_id' => '8']), []))->toBe(['found' => false, 'id' => '8'])
            ->and((new PipedriveDeleteDealNode)->execute($run, pipedriveConfig(['record_id' => '7']), []))->toBe(['deleted' => true, 'id' => '7']);

        $marked = (new PipedriveMarkDealNode)->execute($run, pipedriveConfig(['record_id' => '7', 'status' => 'lost', 'lost_reason' => 'Went with a competitor']), []);

        expect($marked)->toMatchArray(['status' => 'lost', 'lost_time' => '2026-03-01 10:00:00'])
            ->and(pipedriveBody('PUT /deals/7'))->toBe(['status' => 'lost', 'lost_reason' => 'Went with a competitor'])
            ->and(fn () => (new PipedriveMarkDealNode)->execute($run, pipedriveConfig(['record_id' => '7', 'status' => 'archived']), []))->toThrow(InvalidArgumentException::class, 'not a deal status');
    });

    it('lists deals newest first page by page, with the status Pipedrive spells', function () {
        fakePipedrive(['GET /deals' => fn (Request $request) => str_contains($request->url(), 'start=500') ? pipedriveOk([pipedriveDeal(9)]) : pipedriveOk(array_map(fn (int $i): array => pipedriveDeal($i), range(1, 500)), true, 500)]);

        $output = (new PipedriveListDealsNode)->execute(Run::factory()->create(), pipedriveConfig(['limit' => 501]), []);

        expect($output['count'])->toBe(501);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'status=all_not_deleted') && str_contains(urldecode($request->url()), 'sort=add_time DESC') && str_contains($request->url(), 'limit=500'));
    });

    it('searches people by words and unwraps the matches', function () {
        fakePipedrive(['GET /persons/search' => ['success' => true, 'data' => ['items' => [['result_score' => 0.9, 'item' => ['id' => 51, 'name' => 'Ada Lovelace']]]]]]);
        $node = new PipedriveSearchPersonsNode;

        $output = $node->execute(Run::factory()->create(), pipedriveConfig(['term' => 'ada']), []);

        expect($output['persons'][0]['id'])->toBe(51)
            ->and(fn () => $node->execute(Run::factory()->create(), pipedriveConfig(['term' => 'a']), []))->toThrow(InvalidArgumentException::class, 'at least 2 characters');
    });

    it('adds a note to exactly one record', function () {
        fakePipedrive(['POST /notes' => pipedriveOk(['id' => 3, 'content' => 'Sent the quote', 'add_time' => '2026-03-01 10:00:00'])]);
        $node = new PipedriveAddNoteNode;
        $run = Run::factory()->create();

        expect($node->execute($run, pipedriveConfig(['content' => 'Sent the quote', 'deal_id' => '7']), []))->toBe(['id' => 3, 'content' => 'Sent the quote', 'created_at' => '2026-03-01 10:00:00'])
            ->and(pipedriveBody('POST /notes'))->toBe(['content' => 'Sent the quote', 'deal_id' => 7])
            ->and(fn () => $node->execute($run, pipedriveConfig(['content' => 'x']), []))->toThrow(InvalidArgumentException::class, 'exactly one')
            ->and(fn () => $node->execute($run, pipedriveConfig(['content' => 'x', 'deal_id' => '7', 'person_id' => '51']), []))->toThrow(InvalidArgumentException::class, 'exactly one');
    });

    it('says what Pipedrive said when it refuses, even for a 200 that says it failed', function () {
        fakePipedrive(['POST /deals' => fn () => Http::response(['success' => false, 'error' => 'Title is required', 'error_info' => 'Please check the documentation'], 400)]);

        expect(fn () => (new PipedriveCreateDealNode)->execute(Run::factory()->create(), pipedriveConfig(['title' => 'x']), []))->toThrow(RuntimeException::class, 'Pipedrive error: Title is required. Please check the documentation');

        fakePipedrive(['POST /deals' => fn () => Http::response(['success' => false, 'error' => 'Unauthorized'], 200)]);

        expect(fn () => (new PipedriveCreateDealNode)->execute(Run::factory()->create(), pipedriveConfig(['title' => 'x']), []))->toThrow(RuntimeException::class, 'Pipedrive error: Unauthorized.');
    });
});

describe('pipedrive dropdowns', function () {
    it('lists active users, pipelines, and stages of the chosen pipeline or of all of them', function () {
        fakePipedrive([
            'GET /users' => pipedriveOk([['id' => 1, 'name' => 'Ada', 'email' => 'ada@acme.com', 'active_flag' => true], ['id' => 2, 'name' => 'Gone', 'email' => 'gone@acme.com', 'active_flag' => false]]),
            'GET /pipelines' => pipedriveOk([['id' => 1, 'name' => 'Sales'], ['id' => 2, 'name' => 'Renewals']]),
            'GET /stages' => fn (Request $request) => pipedriveOk(array_values(array_filter([['id' => 3, 'name' => 'Qualified', 'pipeline_id' => 1], ['id' => 9, 'name' => 'Upcoming', 'pipeline_id' => 2]], fn (array $stage): bool => ! str_contains($request->url(), 'pipeline_id=') || str_contains($request->url(), 'pipeline_id='.$stage['pipeline_id'])))),
        ]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = fn (array $config = []): NodeOptionsQuery => new NodeOptionsQuery($workspace, User::factory()->create(), $config, 'pd_token');
        $source = app(PipedriveOptions::class);

        expect($source->load('pipedrive.users', $query())->toArray()['options'])->toBe([['value' => '1', 'label' => 'Ada', 'description' => 'ada@acme.com']])
            ->and(array_column($source->load('pipedrive.pipelines', $query())->toArray()['options'], 'label'))->toBe(['Sales', 'Renewals'])
            ->and(array_column($source->load('pipedrive.stages', $query())->toArray()['options'], 'label'))->toBe(['Sales: Qualified', 'Renewals: Upcoming'])
            ->and(array_column($source->load('pipedrive.stages', $query(['pipeline_id' => '1']))->toArray()['options'], 'label'))->toBe(['Qualified']);
    });
});

/**
 * A published workflow whose first step is a Pipedrive record reader switched on
 * as a trigger, with its subscription synced against a faked Pipedrive.
 *
 * @param  array<string, mixed>  $routes
 */
function pipedriveTrigger(string $nodeType = 'pipedrive_list_new_deals', array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakePipedrive($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'pipedrive') ?? Connector::factory()->create(['key' => 'pipedrive']))
        ->create(['data' => ['api_key' => 'pd_token'], 'created_by' => $owner->id]);

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

describe('pipedrive triggers', function () {
    it('polls new deals: a baseline first, then one run for each', function () {
        $trigger = pipedriveTrigger(sync: false);
        $poll = fn (array $ids) => fakePipedrive(['GET /deals' => pipedriveOk(array_map(fn (int $id): array => pipedriveDeal($id), $ids))]);

        $poll([1]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([3, 2, 1]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe([2, 3]);
    });

    it('sorts changed deals by update time, drops the old ones, and makes every change its own event', function () {
        fakePipedrive(['GET /deals' => pipedriveOk([pipedriveDeal(1), pipedriveDeal(2, ['update_time' => now()->subDays(5)->utc()->format('Y-m-d H:i:s')])])]);
        $node = new PipedriveListChangedDealsNode;

        $output = $node->execute(Run::factory()->create(), pipedriveConfig(['changed_within_minutes' => 60]), []);

        expect(array_column($output['deals'], 'id'))->toBe([1])
            ->and(pipedriveQuery('GET /deals'))->toMatchArray(['sort' => 'update_time DESC', 'status' => 'all_not_deleted'])
            ->and($node->triggerItemId(['id' => 1, 'updated_at' => 'a']))->not->toBe($node->triggerItemId(['id' => 1, 'updated_at' => 'b']));
    });
});

describe('pipedrive instant delivery', function () {
    it('creates a webhook for the object and action with a token URL, and starts a poll when it arrives', function () {
        $trigger = pipedriveTrigger(routes: ['POST /webhooks' => pipedriveOk(['id' => 88])]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('deal.added')
            ->and($watch->external_id)->toBe('88')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(pipedriveBody('POST /webhooks'))->toBe(['subscription_url' => url('/api/hooks/apps/pipedrive').'?token='.$watch->secret, 'event_action' => 'added', 'event_object' => 'deal']);

        $body = ['meta' => ['object' => 'deal', 'action' => 'added', 'id' => 7, 'company_id' => 1], 'current' => ['id' => 7]];
        test()->postJson('/api/hooks/apps/pipedrive?token='.$watch->secret, $body)->assertOk();
        test()->postJson('/api/hooks/apps/pipedrive?token='.$watch->secret, $body)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('listens for updated people on a changed-people trigger', function () {
        pipedriveTrigger('pipedrive_list_changed_persons', ['POST /webhooks' => pipedriveOk(['id' => 89])]);

        expect(ProviderWatch::query()->sole()->resource_key)->toBe('person.updated');
    });

    it('refuses a wrong or missing token and is closed while the switch is off', function () {
        pipedriveTrigger(routes: ['POST /webhooks' => pipedriveOk(['id' => 88])]);
        $watch = ProviderWatch::query()->sole();
        $body = ['meta' => ['object' => 'deal', 'action' => 'added', 'id' => 7]];

        test()->postJson('/api/hooks/apps/pipedrive?token=nope', $body)->assertUnauthorized();
        test()->postJson('/api/hooks/apps/pipedrive', $body)->assertUnauthorized();
        config(['triggers.push.pipedrive.enabled' => false]);
        test()->postJson('/api/hooks/apps/pipedrive?token='.$watch->secret, $body)->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('says so when Pipedrive refuses the webhook, and drops the watch', function () {
        $trigger = pipedriveTrigger(routes: ['POST /webhooks' => fn () => Http::response(['success' => false, 'error' => 'You do not have permission to manage webhooks'], 403)]);

        expect($trigger->subscription->status)->not->toBe(SubscriptionStatus::Active)->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('makes the webhook again when Pipedrive turned it off or lost it, and removes it with the watch', function () {
        pipedriveTrigger(routes: ['POST /webhooks' => pipedriveOk(['id' => 88])]);
        $watch = ProviderWatch::query()->sole();
        $source = app(PipedriveSource::class);

        fakePipedrive(['GET /webhooks' => pipedriveOk([['id' => 88, 'is_active' => false]]), 'POST /webhooks' => pipedriveOk(['id' => 90]), 'DELETE /webhooks/88' => ['success' => true]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('90');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && parse_url($request->url(), PHP_URL_PATH) === '/v1/webhooks/88');

        fakePipedrive(['GET /webhooks' => pipedriveOk([['id' => 90, 'is_active' => true]])]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

        fakePipedrive(['DELETE /webhooks/90' => ['success' => true]]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
