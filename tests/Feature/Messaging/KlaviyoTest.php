<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Klaviyo\AbstractKlaviyoNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoAddProfileToListNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoListCampaignsNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoListEventsNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoListProfilesNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoSetFlowStatusNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoSubscribeProfileNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoTrackEventNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoUnsubscribeProfileNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoUpdateProfileNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoUpsertProfileNode;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\KlaviyoOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const KLAVIYO_PROFILE = 'ABC123DEF456GHI789JKL012MN';

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function klaviyoConfig(array $config = []): array
{
    return ['access_token' => 'pk_test', ...$config];
}

/**
 * Answer Klaviyo. A key is "METHOD /path" under /api (a trailing * takes any id);
 * the value is the JSON body, a closure taking the request, or a ready response.
 * A route nobody answers is a 404, so a node that calls something unexpected
 * fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeKlaviyo(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['a.klaviyo.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/api~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['errors' => [['detail' => "Nothing fakes {$key}"]]], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer, 200, ['Content-Type' => 'application/vnd.api+json']));
    }]);
}

/**
 * @return array<string, mixed>
 */
function klaviyoSent(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/api~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return (array) json_decode($found[0][0]->body(), true);
}

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function klaviyoProfile(string $id = KLAVIYO_PROFILE, array $attributes = []): array
{
    return ['type' => 'profile', 'id' => $id, 'attributes' => ['email' => 'ada@example.com', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'location' => ['city' => 'London', 'country' => 'GB'], 'properties' => ['plan' => 'pro'], 'created' => '2026-03-01T10:00:00+00:00', 'updated' => '2026-03-01T10:00:00+00:00', ...$attributes]];
}

describe('klaviyo nodes', function () {
    it('registers the nodes under the klaviyo connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'klaviyo_'));

        expect(count($types))->toBeGreaterThanOrEqual(22);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractKlaviyoNode::class)->and($registry->resolve($type)->category())->toBe('klaviyo');
        }
    });

    it('adds or updates a profile in JSON:API form with the key, the revision and only what is set', function () {
        fakeKlaviyo(['POST /profile-import/' => ['data' => klaviyoProfile()]]);

        $output = (new KlaviyoUpsertProfileNode)->execute(Run::factory()->create(), klaviyoConfig(['email' => ' Ada@Example.com ', 'first_name' => 'Ada', 'phone' => '+1 (415) 555-0123', 'city' => 'London', 'properties' => '{"plan": "pro"}']), []);

        expect($output)->toMatchArray(['id' => KLAVIYO_PROFILE, 'email' => 'ada@example.com', 'city' => 'London', 'country' => 'GB'])
            ->and((array) $output['properties'])->toBe(['plan' => 'pro'])
            ->and(klaviyoSent('POST /profile-import/'))->toBe(['data' => ['type' => 'profile', 'attributes' => [
                'email' => 'ada@example.com', 'phone_number' => '+14155550123', 'first_name' => 'Ada', 'location' => ['city' => 'London'], 'properties' => ['plan' => 'pro'],
            ]]]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Klaviyo-API-Key pk_test') && $request->hasHeader('revision', '2024-10-15') && $request->hasHeader('Content-Type', 'application/vnd.api+json'));
        expect(fn () => (new KlaviyoUpsertProfileNode)->execute(Run::factory()->create(), klaviyoConfig(['email' => '']), []))->toThrow(InvalidArgumentException::class, 'email address is required')
            ->and(fn () => (new KlaviyoUpsertProfileNode)->execute(Run::factory()->create(), klaviyoConfig(['email' => 'a@b.co', 'phone' => '555-0123']), []))->toThrow(InvalidArgumentException::class, 'international format');
    });

    it('finds a profile by email with a quoted filter, and takes an id as it is', function () {
        fakeKlaviyo(['GET /profiles' => ['data' => [klaviyoProfile()]], 'PATCH /profiles/'.KLAVIYO_PROFILE => ['data' => klaviyoProfile()]]);
        $node = new KlaviyoUpdateProfileNode;

        $node->execute(Run::factory()->create(), klaviyoConfig(['profile' => 'Ada@Example.com', 'last_name' => 'Byron']), []);
        expect(klaviyoSent('PATCH /profiles/'.KLAVIYO_PROFILE))->toBe(['data' => ['type' => 'profile', 'id' => KLAVIYO_PROFILE, 'attributes' => ['last_name' => 'Byron']]]);
        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'filter=equals(email,"ada@example.com")'));

        fakeKlaviyo(['GET /profiles' => ['data' => []]]);
        expect(fn () => $node->execute(Run::factory()->create(), klaviyoConfig(['profile' => 'ghost@example.com', 'last_name' => 'x']), []))->toThrow(InvalidArgumentException::class, 'no profile with the email')
            ->and(fn () => $node->execute(Run::factory()->create(), klaviyoConfig(['profile' => 'nope', 'last_name' => 'x']), []))->toThrow(InvalidArgumentException::class, 'not a Klaviyo profile id');
    });

    it('lists profiles newest first with a date and your own filter combined', function () {
        fakeKlaviyo(['GET /profiles' => ['data' => [klaviyoProfile()], 'links' => ['next' => null]]]);

        $output = (new KlaviyoListProfilesNode)->execute(Run::factory()->create(), klaviyoConfig(['created_after' => '2026-03-01 00:00:00', 'filter' => 'equals(properties.plan,"pro")']), []);

        expect($output['count'])->toBe(1);
        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'filter=and(greater-than(created,2026-03-01T00:00:00Z),equals(properties.plan,"pro"))') && str_contains($request->url(), 'sort=-created'));
    });

    it('follows the cursor Klaviyo hands out', function () {
        $calls = 0;
        fakeKlaviyo(['GET /lists' => function (Request $request) use (&$calls) {
            $calls++;

            return str_contains(urldecode($request->url()), 'page[cursor]=c2')
                ? ['data' => [['type' => 'list', 'id' => 'L3', 'attributes' => ['name' => 'Three']]], 'links' => []]
                : ['data' => [['type' => 'list', 'id' => 'L1', 'attributes' => ['name' => 'One']], ['type' => 'list', 'id' => 'L2', 'attributes' => ['name' => 'Two']]], 'links' => ['next' => 'https://a.klaviyo.com/api/lists?page%5Bcursor%5D=c2']];
        }]);

        $output = app(NodeRegistry::class)->resolve('klaviyo_list_lists')->execute(Run::factory()->create(), klaviyoConfig(), []);

        expect($calls)->toBe(2)->and(array_column($output['lists'], 'name'))->toBe(['One', 'Two', 'Three']);
    });

    it('puts a profile on a list, and subscribes with consent and a list relationship', function () {
        fakeKlaviyo(['POST /lists/LIST1/relationships/profiles/' => null, 'POST /profile-subscription-bulk-create-jobs/' => null, 'POST /profile-subscription-bulk-delete-jobs/' => null]);
        $run = Run::factory()->create();

        (new KlaviyoAddProfileToListNode)->execute($run, klaviyoConfig(['list_id' => 'LIST1', 'profile' => KLAVIYO_PROFILE]), []);
        (new KlaviyoSubscribeProfileNode)->execute($run, klaviyoConfig(['list_id' => 'LIST1', 'email' => 'ada@example.com', 'phone' => '+14155550123', 'sms' => true]), []);
        (new KlaviyoUnsubscribeProfileNode)->execute($run, klaviyoConfig(['list_id' => 'LIST1', 'email' => 'ada@example.com']), []);

        expect(klaviyoSent('POST /lists/LIST1/relationships/profiles/'))->toBe(['data' => [['type' => 'profile', 'id' => KLAVIYO_PROFILE]]])
            ->and(klaviyoSent('POST /profile-subscription-bulk-create-jobs/')['data'])->toBe([
                'type' => 'profile-subscription-bulk-create-job',
                'attributes' => ['custom_source' => 'Flows', 'profiles' => ['data' => [['type' => 'profile', 'attributes' => ['email' => 'ada@example.com', 'phone_number' => '+14155550123', 'subscriptions' => ['email' => ['marketing' => ['consent' => 'SUBSCRIBED']], 'sms' => ['marketing' => ['consent' => 'SUBSCRIBED']]]]]]]],
                'relationships' => ['list' => ['data' => ['type' => 'list', 'id' => 'LIST1']]],
            ])
            ->and(klaviyoSent('POST /profile-subscription-bulk-delete-jobs/')['data']['relationships']['list']['data']['id'])->toBe('LIST1')
            ->and(fn () => (new KlaviyoSubscribeProfileNode)->execute($run, klaviyoConfig(['list_id' => 'LIST1', 'email' => 'ada@example.com', 'sms' => true]), []))->toThrow(InvalidArgumentException::class, 'needs a phone number');
    });

    it('tracks an event with its metric, profile, value and unique id', function () {
        fakeKlaviyo(['POST /events/' => null]);

        (new KlaviyoTrackEventNode)->execute(Run::factory()->create(), klaviyoConfig(['metric_name' => 'Placed Order', 'email' => 'ada@example.com', 'properties' => '{"order_id": "1001"}', 'value' => '49.50', 'currency' => 'usd', 'unique_id' => 'order-1001', 'profile_properties' => ['first_name' => 'Ada']]), []);

        expect(klaviyoSent('POST /events/')['data']['attributes'])->toBe([
            'properties' => ['order_id' => '1001'], 'value' => 49.5, 'value_currency' => 'USD', 'unique_id' => 'order-1001',
            'metric' => ['data' => ['type' => 'metric', 'attributes' => ['name' => 'Placed Order']]],
            'profile' => ['data' => ['type' => 'profile', 'attributes' => ['email' => 'ada@example.com', 'first_name' => 'Ada']]],
        ])
            ->and(fn () => (new KlaviyoTrackEventNode)->execute(Run::factory()->create(), klaviyoConfig(['metric_name' => 'X', 'email' => 'a@b.co', 'value' => 'lots']), []))->toThrow(InvalidArgumentException::class, 'must be a number');
    });

    it('lists the events of one metric since a time, with the metric\'s name', function () {
        fakeKlaviyo(['GET /events' => ['data' => [['type' => 'event', 'id' => 'ev1', 'attributes' => ['datetime' => '2026-03-01T10:00:00+00:00', 'event_properties' => ['$value' => 49.5, 'order' => '1001']], 'relationships' => ['profile' => ['data' => ['id' => 'P1']]]]], 'included' => [['type' => 'metric', 'id' => 'MET1', 'attributes' => ['name' => 'Placed Order']]]]]);

        $output = (new KlaviyoListEventsNode)->execute(Run::factory()->create(), klaviyoConfig(['metric_id' => 'MET1', 'since' => '2026-03-01 00:00:00']), []);

        expect($output['events'][0])->toMatchArray(['id' => 'ev1', 'metric' => 'Placed Order', 'occurred_at' => '2026-03-01T10:00:00+00:00', 'profile_id' => 'P1', 'value' => 49.5]);
        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'filter=and(equals(metric_id,"MET1"),greater-or-equal(datetime,2026-03-01T00:00:00Z))') && str_contains($request->url(), 'sort=-datetime') && str_contains($request->url(), 'include=metric'));
    });

    it('lists campaigns of a channel and status with the filter Klaviyo requires', function () {
        fakeKlaviyo(['GET /campaigns' => ['data' => [['id' => 'C1', 'attributes' => ['name' => 'March', 'status' => 'Sent', 'send_time' => '2026-03-01T09:00:00+00:00']]]]]);

        $output = (new KlaviyoListCampaignsNode)->execute(Run::factory()->create(), klaviyoConfig(['channel' => 'email', 'status' => 'Sent']), []);

        expect($output['campaigns'][0])->toMatchArray(['id' => 'C1', 'name' => 'March', 'status' => 'Sent', 'channel' => 'email']);
        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), "filter=and(equals(messages.channel,'email'),equals(status,'Sent'))"));
    });

    it('sets a flow\'s status and refuses a made-up one', function () {
        fakeKlaviyo(['PATCH /flows/FLOW1/' => null]);
        $node = new KlaviyoSetFlowStatusNode;

        $node->execute(Run::factory()->create(), klaviyoConfig(['flow_id' => 'FLOW1', 'status' => 'draft']), []);

        expect(klaviyoSent('PATCH /flows/FLOW1/'))->toBe(['data' => ['type' => 'flow', 'id' => 'FLOW1', 'attributes' => ['status' => 'draft']]])
            ->and(fn () => $node->execute(Run::factory()->create(), klaviyoConfig(['flow_id' => 'FLOW1', 'status' => 'paused']), []))->toThrow(InvalidArgumentException::class, 'not a flow status');
    });

    it('says what Klaviyo said when it refuses', function () {
        fakeKlaviyo(['POST /profile-import/' => fn () => Http::response(['errors' => [['detail' => 'Invalid email address']]], 400)]);

        expect(fn () => (new KlaviyoUpsertProfileNode)->execute(Run::factory()->create(), klaviyoConfig(['email' => 'a@b.co']), []))->toThrow(RuntimeException::class, 'Klaviyo error: Invalid email address.');
    });
});

describe('klaviyo dropdowns', function () {
    it('lists lists and metrics with the header Klaviyo wants, passing on the cursor', function () {
        fakeKlaviyo(['GET /lists' => ['data' => [['id' => 'L1', 'attributes' => ['name' => 'Newsletter']]], 'links' => ['next' => 'https://a.klaviyo.com/api/lists?page%5Bcursor%5D=abc']], 'GET /metrics' => ['data' => [['id' => 'M1', 'attributes' => ['name' => 'Placed Order']]], 'links' => []]]);
        $query = new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), [], 'pk_test');
        $source = app(KlaviyoOptions::class);

        expect($source->load('klaviyo.lists', $query)->toArray())->toBe(['options' => [['value' => 'L1', 'label' => 'Newsletter']], 'next_cursor' => 'abc'])
            ->and($source->load('klaviyo.metrics', $query)->toArray()['options'])->toBe([['value' => 'M1', 'label' => 'Placed Order']]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Klaviyo-API-Key pk_test') && $request->hasHeader('revision', '2024-10-15'));
    });
});

describe('klaviyo triggers', function () {
    it('polls new profiles: a baseline first, then one run for each', function () {
        Queue::fake([FireTriggerEvent::class]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'klaviyo') ?? Connector::factory()->create(['key' => 'klaviyo']))
            ->create(['data' => ['api_key' => 'pk_test'], 'created_by' => $owner->id]);

        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'klaviyo_list_profiles', 'config' => ['credential_id' => $credential->id, 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);

        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        $poll = fn (array $ids) => fakeKlaviyo(['GET /profiles' => ['data' => array_map(fn (string $id): array => klaviyoProfile($id), $ids)]]);

        $poll(['P1']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['P3', 'P2', 'P1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['P2', 'P3']);
    });

    it('is polled only: Klaviyo has no webhook the app can register', function () {
        expect(app(App\Services\Triggers\Push\PushSources::class)->source('klaviyo'))->toBeNull();
    });
});
