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
use App\Nodes\Integrations\Monday\AbstractMondayNode;
use App\Nodes\Integrations\Monday\MondayAddUpdateNode;
use App\Nodes\Integrations\Monday\MondayCreateItemNode;
use App\Nodes\Integrations\Monday\MondayGetBoardNode;
use App\Nodes\Integrations\Monday\MondayGetItemNode;
use App\Nodes\Integrations\Monday\MondayListItemsNode;
use App\Nodes\Integrations\Monday\MondayListUpdatedItemsNode;
use App\Nodes\Integrations\Monday\MondayMoveItemNode;
use App\Nodes\Integrations\Monday\MondaySetColumnNode;
use App\Nodes\Integrations\Monday\MondayUpdateItemNode;
use App\Services\Triggers\Push\MondaySource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\MondayOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function mondayConfig(array $config = []): array
{
    return ['access_token' => 'mon_token', ...$config];
}

/**
 * Answer monday.com. Every call is a POST of a GraphQL query, so a key is a piece
 * of the query text (the first one found wins); the value is the `data` to answer
 * with, or a closure returning a response. A query nobody answers is an error, so
 * a node that asks for something unexpected fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeMonday(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.monday.com/*' => function (Request $request) use ($routes) {
        $query = (string) ($request['query'] ?? '');

        foreach ($routes as $needle => $answer) {
            if (str_contains($query, (string) $needle)) {
                $value = value($answer, $request);

                return $value instanceof GuzzleHttp\Promise\PromiseInterface ? $value : Http::response(['data' => $value ?? []]);
            }
        }

        return Http::response(['errors' => [['message' => 'Nothing fakes this query: '.mb_substr($query, 0, 60)]]]);
    }]);
}

/**
 * The query and variables of the one call whose query holds `$needle`.
 *
 * @return array{0: string, 1: array<string, mixed>}
 */
function mondayCall(string $needle): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => str_contains((string) $pair[0]['query'], $needle)));

    expect($found)->toHaveCount(1);

    return [(string) $found[0][0]['query'], (array) json_decode((string) json_encode($found[0][0]['variables']), true)];
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function mondayItem(string $id = '1001', array $extra = []): array
{
    return ['id' => $id, 'name' => 'Write the email', 'state' => 'active', 'created_at' => now()->subMinutes(10)->toIso8601String(), 'updated_at' => now()->subMinutes(5)->toIso8601String(), 'url' => 'https://acme.monday.com/boards/1/pulses/'.$id, 'group' => ['id' => 'topics', 'title' => 'This week'], 'board' => ['id' => '55'], 'column_values' => [['id' => 'status', 'text' => 'Working on it', 'type' => 'status', 'column' => ['title' => 'Status']], ['id' => 'text', 'text' => 'Hello', 'type' => 'text', 'column' => ['title' => 'Status']]], ...$extra];
}

describe('monday nodes', function () {
    it('registers the nodes under the monday connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'monday_'));

        expect(count($types))->toBeGreaterThanOrEqual(20);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractMondayNode::class)->and($registry->resolve($type)->category())->toBe('monday');
        }
    });

    it('creates an item with the values as a variable, never in the query text', function () {
        fakeMonday(['create_item' => ['create_item' => mondayItem()]]);

        $output = (new MondayCreateItemNode)->execute(Run::factory()->create(), mondayConfig(['board_id' => '55', 'group_id' => 'topics', 'name' => 'Write "the" email', 'column_values' => '{"status": {"label": "Working on it"}}', 'create_labels' => true]), []);
        [$query, $variables] = mondayCall('create_item');

        expect($output)->toMatchArray(['id' => '1001', 'name' => 'Write the email', 'group_id' => 'topics', 'group' => 'This week', 'board_id' => '55'])
            ->and((array) $output['columns'])->toBe(['Status' => 'Working on it', 'Status (text)' => 'Hello'])
            ->and((array) $output['columns_by_id'])->toBe(['status' => 'Working on it', 'text' => 'Hello'])
            ->and($variables)->toBe(['board' => '55', 'group' => 'topics', 'name' => 'Write "the" email', 'values' => '{"status":{"label":"Working on it"}}', 'labels' => true])
            ->and($query)->not->toContain('Write')->not->toContain('Working on it');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'mon_token') && $request->hasHeader('API-Version', '2024-10'));
    });

    it('checks ids and values before calling monday.com', function () {
        fakeMonday([]);
        $run = Run::factory()->create();

        expect(fn () => (new MondayCreateItemNode)->execute($run, mondayConfig(['board_id' => '55) { id } mutation { x', 'name' => 'x']), []))->toThrow(InvalidArgumentException::class, 'must be a monday.com id')
            ->and(fn () => (new MondayCreateItemNode)->execute($run, mondayConfig(['board_id' => '55', 'name' => 'x', 'group_id' => 'a b']), []))->toThrow(InvalidArgumentException::class, 'is not a monday.com id')
            ->and(fn () => (new MondayCreateItemNode)->execute($run, mondayConfig(['board_id' => '55', 'name' => 'x', 'column_values' => '["a"]']), []))->toThrow(InvalidArgumentException::class, 'JSON object')
            ->and(fn () => (new MondayUpdateItemNode)->execute($run, mondayConfig(['board_id' => '55', 'item_id' => '1001']), []))->toThrow(InvalidArgumentException::class, 'JSON object');
        Http::assertNothingSent();
    });

    it('updates several columns, sets one from plain text, and moves an item', function () {
        fakeMonday([
            'change_multiple_column_values' => ['change_multiple_column_values' => mondayItem()],
            'change_simple_column_value' => ['change_simple_column_value' => ['id' => '1001', 'name' => 'Write the email']],
            'move_item_to_group' => ['move_item_to_group' => ['id' => '1001']],
        ]);
        $run = Run::factory()->create();

        (new MondayUpdateItemNode)->execute($run, mondayConfig(['board_id' => '55', 'item_id' => '1001', 'column_values' => ['status' => ['label' => 'Done']]]), []);
        $set = (new MondaySetColumnNode)->execute($run, mondayConfig(['board_id' => '55', 'item_id' => '1001', 'column_id' => 'status', 'value' => 'Done']), []);
        $moved = (new MondayMoveItemNode)->execute($run, mondayConfig(['item_id' => '1001', 'group_id' => 'new_group123']), []);

        expect(mondayCall('change_multiple_column_values')[1])->toMatchArray(['board' => '55', 'item' => '1001', 'values' => '{"status":{"label":"Done"}}'])
            ->and($set)->toBe(['id' => '1001', 'name' => 'Write the email', 'column_id' => 'status', 'value' => 'Done'])
            ->and($moved)->toBe(['moved' => true, 'item_id' => '1001', 'group_id' => 'new_group123']);
    });

    it('reads a board with its groups and columns, and says when it is not found', function () {
        fakeMonday(['boards(ids: $ids) { id name description' => ['boards' => [['id' => '55', 'name' => 'Marketing', 'board_kind' => 'public', 'groups' => [['id' => 'topics', 'title' => 'This week']], 'columns' => [['id' => 'status', 'title' => 'Status', 'type' => 'status']]]]]]);

        $board = (new MondayGetBoardNode)->execute(Run::factory()->create(), mondayConfig(['board_id' => '55']), []);

        expect($board)->toMatchArray(['id' => '55', 'name' => 'Marketing', 'groups' => [['id' => 'topics', 'title' => 'This week']], 'columns' => [['id' => 'status', 'title' => 'Status', 'type' => 'status']]]);

        fakeMonday(['boards(ids: $ids) { id name description' => ['boards' => []]]);

        expect(fn () => (new MondayGetBoardNode)->execute(Run::factory()->create(), mondayConfig(['board_id' => '55']), []))->toThrow(RuntimeException::class, 'that board was not found');
    });

    it('lists a board\'s items newest first by creation, filtered to a group, following the cursor', function () {
        fakeMonday([
            'next_items_page' => ['next_items_page' => ['cursor' => null, 'items' => [mondayItem('1003')]]],
            '$params: ItemsQuery' => ['boards' => [['items_page' => ['cursor' => 'cur1', 'items' => [mondayItem('1001'), mondayItem('1002')]]]]],
        ]);

        $output = (new MondayListItemsNode)->execute(Run::factory()->create(), mondayConfig(['board_id' => '55', 'group_id' => 'topics', 'limit' => 150]), []);
        [, $first] = mondayCall('$params: ItemsQuery');

        expect(array_column($output['items'], 'id'))->toBe(['1001', '1002', '1003'])
            ->and($first['params'])->toBe(['order_by' => [['column_id' => '__creation_log__', 'direction' => 'desc']], 'rules' => [['column_id' => 'group', 'compare_value' => ['topics'], 'operator' => 'any_of']]])
            ->and(mondayCall('next_items_page')[1])->toBe(['limit' => 100, 'cursor' => 'cur1']);
    });

    it('reads one item, comments on it, and says when it is not found', function () {
        fakeMonday([
            'items(ids: $ids) { id name state' => ['items' => [mondayItem()]],
            'create_update' => ['create_update' => ['id' => '9', 'text_body' => 'Thanks', 'created_at' => '2026-03-01T10:00:00Z', 'creator' => ['name' => 'Ada']]],
        ]);
        $run = Run::factory()->create();

        expect((new MondayGetItemNode)->execute($run, mondayConfig(['item_id' => '1001']), [])['id'])->toBe('1001')
            ->and((new MondayAddUpdateNode)->execute($run, mondayConfig(['item_id' => '1001', 'text' => 'Thanks']), []))->toBe(['id' => '9', 'item_id' => '1001', 'text' => 'Thanks', 'created_at' => '2026-03-01T10:00:00Z', 'author' => 'Ada']);

        fakeMonday(['items(ids: $ids) { id name state' => ['items' => []]]);

        expect(fn () => (new MondayGetItemNode)->execute($run, mondayConfig(['item_id' => '1001']), []))->toThrow(RuntimeException::class, 'that item was not found');
    });

    it('says what monday.com said when it refuses, even though it answers 200', function () {
        fakeMonday(['create_item' => fn () => Http::response(['errors' => [['message' => 'Column not found', 'extensions' => ['code' => 'ColumnValueException']]]])]);

        expect(fn () => (new MondayCreateItemNode)->execute(Run::factory()->create(), mondayConfig(['board_id' => '55', 'name' => 'x']), []))->toThrow(RuntimeException::class, 'monday.com error: Column not found (ColumnValueException).');

        fakeMonday(['create_item' => fn () => Http::response(['error_message' => 'Not Authenticated', 'error_code' => 'UserUnauthorizedException'], 200)]);

        expect(fn () => (new MondayCreateItemNode)->execute(Run::factory()->create(), mondayConfig(['board_id' => '55', 'name' => 'x']), []))->toThrow(RuntimeException::class, 'Reconnect monday.com');
    });
});

describe('monday dropdowns', function () {
    it('lists boards with the next page, and the groups and columns of a board', function () {
        fakeMonday([
            'boards(limit: $limit' => ['boards' => [['id' => '55', 'name' => 'Marketing']]],
            'groups { id title } columns' => ['boards' => [['groups' => [['id' => 'topics', 'title' => 'This week']], 'columns' => [['id' => 'status', 'title' => 'Status', 'type' => 'status']]]]],
        ]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = fn (array $config = []): NodeOptionsQuery => new NodeOptionsQuery($workspace, User::factory()->create(), $config, 'mon_token');
        $source = app(MondayOptions::class);

        expect($source->load('monday.boards', $query())->toArray()['options'])->toBe([['value' => '55', 'label' => 'Marketing']])
            ->and($source->load('monday.groups', $query(['board_id' => '55']))->toArray()['options'])->toBe([['value' => 'topics', 'label' => 'This week']])
            ->and($source->load('monday.columns', $query(['board_id' => '55']))->toArray()['options'])->toBe([['value' => 'status', 'label' => 'Status', 'description' => 'status']])
            ->and($source->load('monday.groups', $query())->toArray()['options'])->toBe([]);
    });
});

/**
 * A published workflow whose first step is a monday.com item reader switched on as
 * a trigger, with its subscription synced against a faked monday.com.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 */
function mondayTrigger(string $nodeType = 'monday_list_new_items', array $nodeConfig = [], array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeMonday($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'monday') ?? Connector::factory()->create(['key' => 'monday']))
        ->create(['data' => ['api_key' => 'mon_token'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, 'board_id' => '55', ...$nodeConfig]],
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

describe('monday triggers', function () {
    it('polls new items: a baseline first, then one run for each', function () {
        $trigger = mondayTrigger(sync: false);
        $poll = fn (array $ids) => fakeMonday(['items_page(limit' => ['boards' => [['items_page' => ['cursor' => null, 'items' => array_map(fn (string $id): array => mondayItem($id), $ids)]]]]]);

        $poll(['1001']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['1003', '1002', '1001']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['1002', '1003']);
    });

    it('orders changed items by their last update, and makes every change its own event', function () {
        fakeMonday(['items_page(limit' => ['boards' => [['items_page' => ['cursor' => null, 'items' => [mondayItem('1001'), mondayItem('1002', ['updated_at' => now()->subDays(5)->toIso8601String()])]]]]]]);
        $node = new MondayListUpdatedItemsNode;

        $output = $node->execute(Run::factory()->create(), mondayConfig(['board_id' => '55', 'changed_within_minutes' => 60]), []);

        expect(array_column($output['items'], 'id'))->toBe(['1001'])
            ->and(mondayCall('items_page(limit')[1]['params']['order_by'])->toBe([['column_id' => '__last_updated__', 'direction' => 'desc']])
            ->and($node->triggerItemId(['id' => '1', 'updated_at' => 'a']))->not->toBe($node->triggerItemId(['id' => '1', 'updated_at' => 'b']));
    });
});

describe('monday instant delivery', function () {
    it('creates a webhook for a new item with a token URL, answers the challenge, and starts a poll when it arrives', function () {
        $trigger = mondayTrigger(routes: ['create_webhook' => ['create_webhook' => ['id' => '7001']]]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('created:55')
            ->and($watch->external_id)->toBe('7001')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(mondayCall('create_webhook')[1])->toBe(['board' => '55', 'url' => url('/api/hooks/apps/monday').'?token='.$watch->secret, 'event' => 'create_item']);

        test()->postJson('/api/hooks/apps/monday?token='.$watch->secret, ['challenge' => 'abc123'])->assertOk()->assertExactJson(['challenge' => 'abc123']);
        Queue::assertNotPushed(PollTrigger::class);

        $body = ['event' => ['type' => 'create_pulse', 'boardId' => 55, 'pulseId' => 1001, 'triggerUuid' => 'uuid-1']];
        test()->postJson('/api/hooks/apps/monday?token='.$watch->secret, $body)->assertOk();
        test()->postJson('/api/hooks/apps/monday?token='.$watch->secret, $body)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('creates one webhook for each change event on a changed-item trigger', function () {
        mondayTrigger('monday_list_updated_items', routes: ['create_webhook' => fn (Request $request) => Http::response(['data' => ['create_webhook' => ['id' => $request['variables']['event'] === 'change_name' ? '7003' : '7002']]])]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('changed:55')->and($watch->external_id)->toBe('7002,7003');
        Http::assertSentCount(2);
    });

    it('does not register without a numeric board', function () {
        $trigger = mondayTrigger(nodeConfig: ['board_id' => '{{ trigger.board }}'], routes: ['create_webhook' => ['create_webhook' => ['id' => '7001']]]);

        expect($trigger->subscription)->toBeNull()->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('refuses a wrong or missing token, even for a challenge, and is closed while the switch is off', function () {
        mondayTrigger(routes: ['create_webhook' => ['create_webhook' => ['id' => '7001']]]);
        $watch = ProviderWatch::query()->sole();
        $body = ['event' => ['type' => 'create_pulse', 'triggerUuid' => 'u']];

        test()->postJson('/api/hooks/apps/monday?token=nope', ['challenge' => 'abc'])->assertUnauthorized();
        test()->postJson('/api/hooks/apps/monday', $body)->assertUnauthorized();
        config(['triggers.push.monday.enabled' => false]);
        test()->postJson('/api/hooks/apps/monday?token='.$watch->secret, $body)->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('says so when monday.com refuses the webhook, and drops the watch', function () {
        $trigger = mondayTrigger(routes: ['create_webhook' => fn () => Http::response(['errors' => [['message' => 'URL could not be verified']]])]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)->and($trigger->subscription->last_error)->toContain('URL could not be verified')->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('makes the webhooks again when one is gone, and removes them with the watch', function () {
        mondayTrigger(routes: ['create_webhook' => ['create_webhook' => ['id' => '7001']]]);
        $watch = ProviderWatch::query()->sole();
        $source = app(MondaySource::class);

        fakeMonday(['webhooks(board_id' => ['webhooks' => [['id' => '9999']]], 'create_webhook' => ['create_webhook' => ['id' => '7002']], 'delete_webhook' => ['delete_webhook' => ['id' => '7001']]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('7002');
        expect(mondayCall('delete_webhook')[1])->toBe(['id' => '7001']);

        fakeMonday(['webhooks(board_id' => ['webhooks' => [['id' => '7002']]]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        Http::assertSentCount(1);

        fakeMonday(['delete_webhook' => ['delete_webhook' => ['id' => '7002']]]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
