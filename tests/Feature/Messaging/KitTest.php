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
use App\Nodes\Integrations\Kit\AbstractKitNode;
use App\Nodes\Integrations\Kit\KitAddSubscriberToFormNode;
use App\Nodes\Integrations\Kit\KitCreateBroadcastNode;
use App\Nodes\Integrations\Kit\KitCreateOrUpdateSubscriberNode;
use App\Nodes\Integrations\Kit\KitCreatePurchaseNode;
use App\Nodes\Integrations\Kit\KitListSubscribersNode;
use App\Nodes\Integrations\Kit\KitTagSubscriberNode;
use App\Nodes\Integrations\Kit\KitUntagSubscriberNode;
use App\Nodes\Integrations\Kit\KitUpdateSubscriberNode;
use App\Services\Triggers\Push\KitSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\KitOptions;
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
function kitConfig(array $config = []): array
{
    return ['access_token' => 'kit_key', ...$config];
}

/**
 * Answer Kit. A key is "METHOD /path" under /v4 (a trailing * takes any id); the
 * value is the JSON body, a closure taking the request, or a ready response.
 * A route nobody answers is a 404, so a node that calls something unexpected
 * fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeKit(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.kit.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/v4~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['errors' => ["Nothing fakes {$key}"]], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

/**
 * @return array<int|string, mixed>
 */
function kitSent(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/v4~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return (array) json_decode($found[0][0]->body(), true);
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function kitSubscriber(int $id = 1, array $extra = []): array
{
    return ['id' => $id, 'email_address' => "s{$id}@example.com", 'first_name' => 'Ada', 'state' => 'active', 'created_at' => '2026-03-01T10:00:00Z', 'updated_at' => '2026-03-01T10:00:00Z', 'fields' => ['company' => 'Acme'], ...$extra];
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function kitPage(string $key, array $items, ?string $next = null): array
{
    return [$key => $items, 'pagination' => ['has_next_page' => $next !== null, 'end_cursor' => $next, 'per_page' => 1000]];
}

describe('kit nodes', function () {
    it('registers the nodes under the kit connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'kit_'));

        expect(count($types))->toBeGreaterThanOrEqual(30);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractKitNode::class)->and($registry->resolve($type)->category())->toBe('kit');
        }
    });

    it('adds or updates a subscriber with the key header and only what is set', function () {
        fakeKit(['POST /subscribers' => ['subscriber' => kitSubscriber(7)]]);

        $output = (new KitCreateOrUpdateSubscriberNode)->execute(Run::factory()->create(), kitConfig(['email' => ' Ada@Example.com ', 'first_name' => 'Ada', 'fields' => '{"company": "Acme"}', 'state' => 'active']), []);

        expect($output)->toMatchArray(['id' => 7, 'email' => 's7@example.com', 'first_name' => 'Ada', 'state' => 'active'])
            ->and(kitSent('POST /subscribers'))->toBe(['email_address' => 'ada@example.com', 'first_name' => 'Ada', 'state' => 'active', 'fields' => ['company' => 'Acme']])
            ->and(fn () => (new KitCreateOrUpdateSubscriberNode)->execute(Run::factory()->create(), kitConfig(['email' => 'nope']), []))->toThrow(InvalidArgumentException::class, 'is not an email address');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Kit-Api-Key', 'kit_key'));
    });

    it('finds a subscriber by email once, or takes an id as it is', function () {
        fakeKit(['GET /subscribers' => kitPage('subscribers', [kitSubscriber(9)]), 'PUT /subscribers/9' => ['subscriber' => kitSubscriber(9, ['first_name' => 'Grace'])]]);
        $node = new KitUpdateSubscriberNode;

        $node->execute(Run::factory()->create(), kitConfig(['subscriber' => 'Ada@Example.com', 'first_name' => 'Grace']), []);
        expect(kitSent('PUT /subscribers/9'))->toBe(['first_name' => 'Grace']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'email_address=ada%40example.com') && str_contains($request->url(), 'status=all'));

        fakeKit(['PUT /subscribers/9' => ['subscriber' => kitSubscriber(9)]]);
        $node->execute(Run::factory()->create(), kitConfig(['subscriber' => '9', 'first_name' => 'Grace']), []);
        Http::assertSentCount(1);

        fakeKit(['GET /subscribers' => kitPage('subscribers', [])]);
        expect(fn () => $node->execute(Run::factory()->create(), kitConfig(['subscriber' => 'ghost@example.com', 'first_name' => 'x']), []))->toThrow(InvalidArgumentException::class, 'no subscriber with the email')
            ->and(fn () => $node->execute(Run::factory()->create(), kitConfig(['subscriber' => '9']), []))->toThrow(InvalidArgumentException::class, 'Change at least one thing');
    });

    it('tags by email in one call, by id on a known subscriber, and untags by id', function () {
        fakeKit(['POST /tags/5/subscribers' => ['subscriber' => kitSubscriber()], 'POST /tags/5/subscribers/9' => ['subscriber' => kitSubscriber(9)], 'GET /subscribers' => kitPage('subscribers', [kitSubscriber(9)]), 'DELETE /tags/5/subscribers/9' => null]);
        $run = Run::factory()->create();

        (new KitTagSubscriberNode)->execute($run, kitConfig(['tag_id' => 5, 'subscriber' => 'ada@example.com']), []);
        expect(kitSent('POST /tags/5/subscribers'))->toBe(['email_address' => 'ada@example.com']);

        (new KitTagSubscriberNode)->execute($run, kitConfig(['tag_id' => 5, 'subscriber' => '9']), []);
        $untagged = (new KitUntagSubscriberNode)->execute($run, kitConfig(['tag_id' => 5, 'subscriber' => 'ada@example.com']), []);

        expect($untagged)->toBe(['untagged' => true, 'tag_id' => 5, 'subscriber_id' => 9])
            ->and(fn () => (new KitTagSubscriberNode)->execute($run, kitConfig(['subscriber' => 'ada@example.com']), []))->toThrow(InvalidArgumentException::class, 'The tag is required');
    });

    it('adds a subscriber to a form by email', function () {
        fakeKit(['POST /forms/3/subscribers' => ['subscriber' => kitSubscriber()]]);

        $output = (new KitAddSubscriberToFormNode)->execute(Run::factory()->create(), kitConfig(['form_id' => 3, 'subscriber' => 'ada@example.com']), []);

        expect($output['added'])->toBeTrue()->and(kitSent('POST /forms/3/subscribers'))->toBe(['email_address' => 'ada@example.com']);
    });

    it('lists subscribers across pages with the cursor and the filters Kit understands', function () {
        $calls = 0;
        fakeKit(['GET /subscribers' => function (Request $request) use (&$calls) {
            $calls++;

            return str_contains($request->url(), 'after=cur1') ? kitPage('subscribers', [kitSubscriber(3)]) : kitPage('subscribers', [kitSubscriber(1), kitSubscriber(2)], 'cur1');
        }]);

        $output = (new KitListSubscribersNode)->execute(Run::factory()->create(), kitConfig(['status' => 'cancelled', 'created_after' => '2026-03-01 00:00:00', 'limit' => 10]), []);

        expect($calls)->toBe(2)->and(array_column($output['subscribers'], 'id'))->toBe([1, 2, 3]);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'status=cancelled') && str_contains(urldecode($request->url()), 'created_after=2026-03-01T00:00:00') && str_contains($request->url(), 'sort_order=desc'));
    });

    it('creates a broadcast as a draft, or scheduled to people with some tags, and refuses a past time', function () {
        fakeKit(['POST /broadcasts' => ['broadcast' => ['id' => 11, 'subject' => 'News']]]);
        $node = new KitCreateBroadcastNode;
        $run = Run::factory()->create();
        $at = now()->addDay()->startOfSecond();

        $node->execute($run, kitConfig(['subject' => 'News', 'content' => '<p>Hi</p>', 'send_at' => $at->toIso8601String(), 'tag_ids' => '4, 5, x', 'email_template_id' => 2]), []);

        expect(kitSent('POST /broadcasts'))->toBe([
            'subject' => 'News', 'content' => '<p>Hi</p>', 'send_at' => $at->utc()->toIso8601String(), 'email_template_id' => 2,
            'subscriber_filter' => [['all' => [['type' => 'tag', 'ids' => [4, 5]]]]],
        ])
            ->and(fn () => $node->execute($run, kitConfig(['subject' => 'News', 'content' => 'x', 'send_at' => '2020-01-01 10:00:00']), []))->toThrow(InvalidArgumentException::class, 'in the future');

        fakeKit(['POST /broadcasts' => ['broadcast' => ['id' => 12]]]);
        $node->execute($run, kitConfig(['subject' => 'Draft', 'content' => 'x']), []);
        expect(kitSent('POST /broadcasts'))->toBe(['subject' => 'Draft', 'content' => 'x']);
    });

    it('records a purchase with a product line', function () {
        fakeKit(['POST /purchases' => ['purchase' => ['id' => 4, 'transaction_id' => 'order-1', 'status' => 'paid', 'total' => 49.0, 'email_address' => 'ada@example.com']]]);

        $output = (new KitCreatePurchaseNode)->execute(Run::factory()->create(), kitConfig(['email' => 'ada@example.com', 'transaction_id' => 'order-1', 'product_name' => 'Course', 'unit_price' => '49.00', 'currency' => 'usd']), []);

        expect($output)->toMatchArray(['id' => 4, 'total' => 49.0])
            ->and(kitSent('POST /purchases')['purchase'])->toMatchArray(['email_address' => 'ada@example.com', 'transaction_id' => 'order-1', 'status' => 'paid', 'currency' => 'USD'])
            ->and(kitSent('POST /purchases')['purchase']['products'][0])->toMatchArray(['name' => 'Course', 'quantity' => 1, 'unit_price' => 49.0])
            ->and(fn () => (new KitCreatePurchaseNode)->execute(Run::factory()->create(), kitConfig(['email' => 'a@b.co', 'transaction_id' => 't', 'product_name' => 'p', 'unit_price' => 'free']), []))->toThrow(InvalidArgumentException::class, 'must be a number');
    });

    it('says what Kit said when it refuses', function () {
        fakeKit(['POST /subscribers' => fn () => Http::response(['errors' => ['Email address is invalid']], 422)]);

        expect(fn () => (new KitCreateOrUpdateSubscriberNode)->execute(Run::factory()->create(), kitConfig(['email' => 'a@b.co']), []))->toThrow(RuntimeException::class, 'Kit error: Email address is invalid.');
    });
});

describe('kit dropdowns', function () {
    it('lists tags with the next page cursor and filters by what was typed', function () {
        fakeKit(['GET /tags' => kitPage('tags', [['id' => 1, 'name' => 'Customer'], ['id' => 2, 'name' => 'Lead']], 'next9')]);
        $query = fn (?string $search = null): NodeOptionsQuery => new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), [], 'kit_key', $search);
        $source = app(KitOptions::class);

        expect($source->load('kit.tags', $query())->toArray())->toBe(['options' => [['value' => '1', 'label' => 'Customer'], ['value' => '2', 'label' => 'Lead']], 'next_cursor' => 'next9'])
            ->and(array_column($source->load('kit.tags', $query('lea'))->toArray()['options'], 'label'))->toBe(['Lead']);
    });
});

/**
 * A published workflow whose first step is a Kit reader switched on as a trigger,
 * with its subscription synced against a faked Kit.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 */
function kitTrigger(string $nodeType = 'kit_list_subscribers', array $nodeConfig = [], array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeKit($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'kit') ?? Connector::factory()->create(['key' => 'kit']))
        ->create(['data' => ['api_key' => 'kit_key'], 'created_by' => $owner->id]);

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

describe('kit triggers', function () {
    it('polls new subscribers: a baseline first, then one run for each', function () {
        $trigger = kitTrigger(sync: false);
        $poll = fn (array $ids) => fakeKit(['GET /subscribers' => kitPage('subscribers', array_map(fn (int $id): array => kitSubscriber($id), $ids))]);

        $poll([1]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([3, 2, 1]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe([2, 3]);
    });

    it('treats a person who is tagged again, or leaves again, as a new event', function () {
        $tagged = app(NodeRegistry::class)->resolve('kit_list_tagged_subscribers');
        $left = app(NodeRegistry::class)->resolve('kit_list_unsubscribes');

        expect($tagged->triggerItemId(['id' => 1, 'tagged_at' => 'a']))->not->toBe($tagged->triggerItemId(['id' => 1, 'tagged_at' => 'b']))
            ->and($left->triggerItemId(['id' => 1, 'updated_at' => 'a']))->not->toBe($left->triggerItemId(['id' => 1, 'updated_at' => 'b']));
    });

    it('reads unsubscribes and bounces by state, changed since a look-back', function (string $type, string $status) {
        fakeKit(['GET /subscribers' => kitPage('subscribers', [kitSubscriber(1)])]);
        $node = app(NodeRegistry::class)->resolve($type);

        $output = $node->execute(Run::factory()->create(), kitConfig($node->triggerReadConfig([])), []);

        expect($output['count'])->toBe(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), "status={$status}") && str_contains($request->url(), 'sort_field=updated_at') && str_contains($request->url(), 'updated_after='));
    })->with([['kit_list_unsubscribes', 'cancelled'], ['kit_list_bounced_subscribers', 'bounced']]);
});

describe('kit instant delivery', function () {
    it('registers one webhook per event with a token URL, and starts a poll when it arrives', function () {
        $trigger = kitTrigger(routes: ['POST /webhooks' => ['webhook' => ['id' => 55]]]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('subscriber.subscriber_activate')
            ->and($watch->external_id)->toBe('55')
            ->and($trigger->subscription->routing_key)->toBe(hash('sha256', $watch->secret))
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(kitSent('POST /webhooks'))->toBe(['target_url' => url('/api/hooks/apps/kit').'?token='.$watch->secret, 'event' => ['name' => 'subscriber.subscriber_activate']]);

        test()->postJson('/api/hooks/apps/kit?token='.$watch->secret, ['subscriber' => ['id' => 1, 'updated_at' => 'x']])->assertOk();
        test()->postJson('/api/hooks/apps/kit?token='.$watch->secret, ['subscriber' => ['id' => 1, 'updated_at' => 'x']])->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('names the tag or form for the events that need one, and keeps polling when it is not named', function () {
        kitTrigger('kit_list_tagged_subscribers', ['tag_id' => 12], ['POST /webhooks' => ['webhook' => ['id' => 56]]]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('subscriber.tag_add:tag_id:12')
            ->and(kitSent('POST /webhooks')['event'])->toBe(['name' => 'subscriber.tag_add', 'tag_id' => 12])
            ->and(kitTrigger('kit_list_form_subscribers', ['form_id' => '{{ trigger.form }}'])->subscription)->toBeNull();
    });

    it('refuses a wrong or missing token and is closed while the switch is off', function () {
        kitTrigger(routes: ['POST /webhooks' => ['webhook' => ['id' => 55]]]);
        $watch = ProviderWatch::query()->sole();

        test()->postJson('/api/hooks/apps/kit?token=nope', ['subscriber' => ['id' => 1]])->assertUnauthorized();
        test()->postJson('/api/hooks/apps/kit', ['subscriber' => ['id' => 1]])->assertUnauthorized();
        config(['triggers.push.kit.enabled' => false]);
        test()->postJson('/api/hooks/apps/kit?token='.$watch->secret, ['subscriber' => ['id' => 1]])->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('says so when Kit refuses the webhook, and drops the watch', function () {
        $trigger = kitTrigger(routes: ['POST /webhooks' => fn () => Http::response(['errors' => ['Target url is invalid']], 422)]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)->and($trigger->subscription->last_error)->toContain('422')->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('makes the webhook again when Kit no longer lists it, and removes it with the watch', function () {
        kitTrigger(routes: ['POST /webhooks' => ['webhook' => ['id' => 55]]]);
        $watch = ProviderWatch::query()->sole();
        $source = app(KitSource::class);

        fakeKit(['GET /webhooks' => ['webhooks' => [['id' => 99, 'active' => true]]], 'POST /webhooks' => ['webhook' => ['id' => 60]]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('60')->and($watch->fresh()->status)->toBe(SubscriptionStatus::Active);

        fakeKit(['GET /webhooks' => ['webhooks' => [['id' => 60, 'active' => true]]]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

        fakeKit(['DELETE /webhooks/60' => null]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
