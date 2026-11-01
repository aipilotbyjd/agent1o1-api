<?php

use App\Enums\Agents\ActionEffect;
use App\Enums\Triggers\SubscriptionStatus;
use App\Exceptions\ConnectorException;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderEvent;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Shopify\AbstractShopifyNode;
use App\Nodes\Integrations\Shopify\ShopifyAdjustInventoryLevelNode;
use App\Nodes\Integrations\Shopify\ShopifyCancelOrderNode;
use App\Nodes\Integrations\Shopify\ShopifyCompleteDraftOrderNode;
use App\Nodes\Integrations\Shopify\ShopifyCreateCustomerNode;
use App\Nodes\Integrations\Shopify\ShopifyCreateFulfillmentNode;
use App\Nodes\Integrations\Shopify\ShopifyCreateOrderNode;
use App\Nodes\Integrations\Shopify\ShopifyCreateProductNode;
use App\Nodes\Integrations\Shopify\ShopifyCreateRefundNode;
use App\Nodes\Integrations\Shopify\ShopifyCreateWebhookNode;
use App\Nodes\Integrations\Shopify\ShopifyGetOrderNode;
use App\Nodes\Integrations\Shopify\ShopifyListOrdersNode;
use App\Nodes\Integrations\Shopify\ShopifyOrderPaidNode;
use App\Nodes\Integrations\Shopify\ShopifySetInventoryLevelNode;
use App\Nodes\Integrations\Shopify\ShopifyUpdateCustomerNode;
use App\Nodes\Integrations\Shopify\ShopifyUpdateOrderNode;
use App\Nodes\Integrations\Shopify\ShopifyUpdateVariantNode;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Triggers\Push\ShopifySource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ShopifyOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const SHOPIFY_SHOP = 'acme.myshopify.com';
const SHOPIFY_TOKEN = 'shpat_test';
const SHOPIFY_SECRET = 'shpss_secret';

/**
 * A run with a connected store in its workspace.
 *
 * @param  array<string, mixed>  $data
 */
function shopifyRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'shopify') ?? Connector::factory()->create(['key' => 'shopify']))->id,
        'data' => ['shop' => SHOPIFY_SHOP, 'api_key' => SHOPIFY_TOKEN, 'api_secret' => SHOPIFY_SECRET, ...$data],
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Shopify. A key is "METHOD /path" under the Admin API version; the value
 * is the JSON body, a closure taking the request, or a ready response. A route
 * nobody answers is a 404, so a node that calls something unexpected fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeShopify(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['*myshopify.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/admin/api/'.preg_quote(AbstractShopifyNode::API_VERSION, '~').'~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['errors' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer ?? []);
    }]);
}

/**
 * What was sent to a route: the query of a GET, the JSON body of a write.
 *
 * @return array<int|string, mixed>
 */
function shopifySent(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/admin/api/[^/]+~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    if ($found[0][0]->method() === 'GET') {
        parse_str((string) parse_url($found[0][0]->url(), PHP_URL_QUERY), $query);

        return $query;
    }

    return (array) json_decode($found[0][0]->body(), true);
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function shopifyOrder(int $id = 1001, array $extra = []): array
{
    return [
        'id' => $id, 'name' => '#'.$id, 'email' => 'ada@example.com', 'financial_status' => 'paid', 'total_price' => '19.99', 'currency' => 'USD',
        'created_at' => '2026-03-01T10:00:00+00:00', 'updated_at' => '2026-03-01T10:05:00+00:00',
        'line_items' => [['id' => 11, 'title' => 'T-shirt', 'quantity' => 1]],
        ...$extra,
    ];
}

describe('shopify nodes', function () {
    it('registers the nodes under the shopify connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'shopify_'));

        expect(count($types))->toBeGreaterThanOrEqual(41);

        foreach ($types as $type) {
            $node = $registry->resolve($type);

            expect($node)->toBeInstanceOf(AbstractShopifyNode::class)
                ->and($node->category())->toBe('shopify')
                ->and($node->configSchema()['properties'])->toHaveKey('credential_id');
        }
    });

    it('takes only a myshopify.com store, however it was typed', function () {
        expect(AbstractShopifyNode::normalizeShop('acme'))->toBe('acme.myshopify.com')
            ->and(AbstractShopifyNode::normalizeShop(' https://Acme.myshopify.com/admin/orders '))->toBe('acme.myshopify.com')
            ->and(AbstractShopifyNode::normalizeShop('acme.myshopify.com.evil.test'))->toBeNull()
            ->and(AbstractShopifyNode::normalizeShop('evil.test'))->toBeNull()
            ->and(AbstractShopifyNode::normalizeShop('acme.myshopify.com@evil.test'))->toBeNull()
            ->and(AbstractShopifyNode::normalizeShop(''))->toBeNull();
    });

    it('reads an order from the store, at the pinned API version, with the access token', function () {
        fakeShopify(['GET /orders/1001.json' => ['order' => shopifyOrder()]]);

        $order = (new ShopifyGetOrderNode)->execute(shopifyRun(), ['order_id' => '1001'], []);

        expect($order['name'])->toBe('#1001');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://acme.myshopify.com/admin/api/'.AbstractShopifyNode::API_VERSION.'/orders/1001.json' && $request->hasHeader('X-Shopify-Access-Token', SHOPIFY_TOKEN));
    });

    it('accepts a gid and refuses an id that could change the path', function () {
        fakeShopify(['GET /orders/1001.json' => ['order' => shopifyOrder()]]);
        $run = shopifyRun();

        (new ShopifyGetOrderNode)->execute($run, ['order_id' => 'gid://shopify/Order/1001'], []);

        expect(fn () => (new ShopifyGetOrderNode)->execute($run, ['order_id' => '1001/../shop'], []))->toThrow(InvalidArgumentException::class, 'is not a Shopify order id')
            ->and(fn () => (new ShopifyGetOrderNode)->execute($run, [], []))->toThrow(InvalidArgumentException::class, 'required');
    });

    it('needs a store address on the connection', function () {
        fakeShopify([]);

        expect(fn () => (new ShopifyGetOrderNode)->execute(shopifyRun(['shop' => 'evil.test']), ['order_id' => '1'], []))->toThrow(InvalidArgumentException::class, 'store address');
        Http::assertNothingSent();
    });

    it('lists orders across pages by following the link header, newest first', function () {
        $queries = [];
        fakeShopify(['GET /orders.json' => function (Request $request) use (&$queries) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $queries[] = $query;

            return isset($query['page_info'])
                ? Http::response(['orders' => [shopifyOrder(3, ['created_at' => '2026-03-03T00:00:00+00:00'])]])
                : Http::response(['orders' => [shopifyOrder(1, ['created_at' => '2026-03-01T00:00:00+00:00']), shopifyOrder(2, ['created_at' => '2026-03-02T00:00:00+00:00'])]], 200, ['Link' => '<https://acme.myshopify.com/admin/api/'.AbstractShopifyNode::API_VERSION.'/orders.json?limit=250&page_info=CURSOR2>; rel="next"']);
        }]);

        $output = (new ShopifyListOrdersNode)->execute(shopifyRun(), ['limit' => 10, 'since' => '2026-03-01 00:00:00', 'financial_status' => 'paid'], []);

        expect(array_column($output['orders'], 'id'))->toBe([3, 2, 1])
            ->and($output)->toMatchArray(['count' => 3, 'has_more' => false])
            ->and($queries[0])->toMatchArray(['status' => 'any', 'financial_status' => 'paid', 'limit' => '10'])
            ->and($queries[0]['created_at_min'])->toBe('2026-03-01T00:00:00+00:00')
            ->and($queries[1])->toBe(['page_info' => 'CURSOR2', 'limit' => '8']);
    });

    it('says when there is more than the limit', function () {
        fakeShopify(['GET /orders.json' => fn () => Http::response(['orders' => [shopifyOrder(1), shopifyOrder(2)]], 200, ['Link' => '<https://acme.myshopify.com/x.json?page_info=NEXT>; rel="next"'])]);

        $output = (new ShopifyListOrdersNode)->execute(shopifyRun(), ['limit' => 2], []);

        expect($output['count'])->toBe(2)->and($output['has_more'])->toBeTrue();
    });

    it('reads the cursor only from the next link', function () {
        expect(AbstractShopifyNode::nextCursor('<https://a.myshopify.com/x.json?page_info=PREV>; rel="previous", <https://a.myshopify.com/x.json?page_info=NEXT>; rel="next"'))->toBe('NEXT')
            ->and(AbstractShopifyNode::nextCursor('<https://a.myshopify.com/x.json?page_info=PREV>; rel="previous"'))->toBeNull()
            ->and(AbstractShopifyNode::nextCursor(null))->toBeNull();
    });

    it('creates an order with JSON booleans and integer ids, and declares a receipt as reaching the customer', function () {
        fakeShopify(['POST /orders.json' => ['order' => shopifyOrder()]]);
        $node = new ShopifyCreateOrderNode;
        $config = ['line_items' => '[{"variant_id": "808950810", "quantity": 2}, {"title": "Gift wrap", "price": "3.50"}]', 'customer_id' => '207119551', 'email' => 'ignored@example.com', 'financial_status' => 'paid', 'send_receipt' => true, 'tags' => ['vip', 'web']];

        $node->execute(shopifyRun(), $config, []);

        expect(shopifySent('POST /orders.json'))->toBe(['order' => [
            'line_items' => [['variant_id' => 808950810, 'quantity' => 2], ['title' => 'Gift wrap', 'price' => '3.50', 'quantity' => 1]],
            'customer' => ['id' => 207119551],
            'financial_status' => 'paid',
            'send_receipt' => true,
            'tags' => 'vip, web',
        ]])->and($node->effect($config))->toBe(ActionEffect::External)
            ->and($node->effect([...$config, 'send_receipt' => false]))->toBe(ActionEffect::Write);
    });

    it('checks an order before sending it', function () {
        fakeShopify([]);
        $run = shopifyRun();
        $node = new ShopifyCreateOrderNode;

        expect(fn () => $node->execute($run, ['line_items' => '[]'], []))->toThrow(InvalidArgumentException::class, 'at least one')
            ->and(fn () => $node->execute($run, ['line_items' => '[{"quantity": 1}]'], []))->toThrow(InvalidArgumentException::class, 'variant_id')
            ->and(fn () => $node->execute($run, ['line_items' => '[{"title": "x", "price": "free"}]'], []))->toThrow(InvalidArgumentException::class, 'amount such as')
            ->and(fn () => $node->execute($run, ['line_items' => '[{"variant_id": 1}]', 'financial_status' => 'refunded'], []))->toThrow(InvalidArgumentException::class, 'pending or paid')
            ->and(fn () => $node->execute($run, ['line_items' => 'not json'], []))->toThrow(InvalidArgumentException::class, 'valid JSON');
        Http::assertNothingSent();
    });

    it('updates only what is filled in', function () {
        fakeShopify(['PUT /orders/1001.json' => ['order' => shopifyOrder()]]);
        $run = shopifyRun();

        (new ShopifyUpdateOrderNode)->execute($run, ['order_id' => '1001', 'note' => 'Gift', 'tags' => 'a, b'], []);

        expect(shopifySent('PUT /orders/1001.json'))->toBe(['order' => ['id' => 1001, 'note' => 'Gift', 'tags' => 'a, b']])
            ->and(fn () => (new ShopifyUpdateOrderNode)->execute($run, ['order_id' => '1001'], []))->toThrow(InvalidArgumentException::class, 'something to change');
    });

    it('cancels an order with the reason and real booleans', function () {
        fakeShopify(['POST /orders/1001/cancel.json' => ['id' => 1001, 'cancelled_at' => '2026-03-01T10:00:00Z']]);
        $node = new ShopifyCancelOrderNode;

        $node->execute(shopifyRun(), ['order_id' => '1001', 'reason' => 'inventory', 'restock' => true], []);

        expect(shopifySent('POST /orders/1001/cancel.json'))->toBe(['reason' => 'inventory', 'restock' => true, 'email' => false])
            ->and($node->effect([]))->toBe(ActionEffect::Destructive)
            ->and(fn () => $node->execute(shopifyRun(), ['order_id' => '1', 'reason' => 'boredom'], []))->toThrow(InvalidArgumentException::class, 'reasons');
    });

    it('ships what is left to ship, with tracking, and tells the customer by default', function () {
        fakeShopify([
            'GET /orders/1001/fulfillment_orders.json' => ['fulfillment_orders' => [['id' => 51, 'status' => 'open'], ['id' => 52, 'status' => 'closed'], ['id' => 53, 'status' => 'in_progress']]],
            'POST /fulfillments.json' => ['fulfillment' => ['id' => 9, 'status' => 'success']],
        ]);
        $node = new ShopifyCreateFulfillmentNode;

        $output = $node->execute(shopifyRun(), ['order_id' => '1001', 'tracking_number' => 'ZZ123', 'tracking_company' => 'UPS'], []);

        expect($output['id'])->toBe(9)
            ->and(shopifySent('POST /fulfillments.json'))->toBe(['fulfillment' => [
                'line_items_by_fulfillment_order' => [['fulfillment_order_id' => 51], ['fulfillment_order_id' => 53]],
                'tracking_info' => ['number' => 'ZZ123', 'company' => 'UPS'],
                'notify_customer' => true,
            ]])->and($node->effect([]))->toBe(ActionEffect::External);
    });

    it('refuses to fulfil an order with nothing left to ship', function () {
        fakeShopify(['GET /orders/1001/fulfillment_orders.json' => ['fulfillment_orders' => [['id' => 52, 'status' => 'closed']]]]);

        expect(fn () => (new ShopifyCreateFulfillmentNode)->execute(shopifyRun(), ['order_id' => '1001'], []))->toThrow(RuntimeException::class, 'nothing is left to ship');
    });

    it('refunds the items and money named, and refuses an empty refund', function () {
        fakeShopify(['POST /orders/1001/refunds.json' => ['refund' => ['id' => 7]]]);
        $run = shopifyRun();
        $node = new ShopifyCreateRefundNode;

        $node->execute($run, [
            'order_id' => '1001', 'note' => 'Damaged', 'notify_customer' => true, 'full_shipping_refund' => true,
            'refund_line_items' => '[{"line_item_id": 11, "quantity": 1, "restock_type": "return"}]',
            'transactions' => '[{"parent_id": 5, "amount": "19.99", "gateway": "bogus"}]',
        ], []);

        expect(shopifySent('POST /orders/1001/refunds.json'))->toBe(['refund' => [
            'refund_line_items' => [['line_item_id' => 11, 'quantity' => 1, 'restock_type' => 'return']],
            'transactions' => [['parent_id' => 5, 'amount' => '19.99', 'gateway' => 'bogus', 'kind' => 'refund']],
            'shipping' => ['full_refund' => true],
            'note' => 'Damaged',
            'notify' => true,
        ]])->and(fn () => $node->execute($run, ['order_id' => '1001'], []))->toThrow(InvalidArgumentException::class, 'Name the items')
            ->and(fn () => $node->execute($run, ['order_id' => '1001', 'transactions' => '[{"amount": "lots"}]'], []))->toThrow(InvalidArgumentException::class, 'amount such as')
            ->and($node->effect([]))->toBe(ActionEffect::External);
    });

    it('creates and updates customers with a real boolean for marketing consent', function () {
        fakeShopify(['POST /customers.json' => ['customer' => ['id' => 1]], 'PUT /customers/207119551.json' => ['customer' => ['id' => 207119551]]]);
        $run = shopifyRun();

        (new ShopifyCreateCustomerNode)->execute($run, ['email' => 'ada@example.com', 'first_name' => 'Ada', 'tags' => ['vip'], 'accepts_marketing' => 'true'], []);
        (new ShopifyUpdateCustomerNode)->execute($run, ['customer_id' => '207119551', 'last_name' => 'Lovelace'], []);

        expect(shopifySent('POST /customers.json'))->toBe(['customer' => ['email' => 'ada@example.com', 'first_name' => 'Ada', 'tags' => 'vip', 'accepts_marketing' => true]])
            ->and(shopifySent('PUT /customers/207119551.json'))->toBe(['customer' => ['id' => 207119551, 'last_name' => 'Lovelace']])
            ->and(fn () => (new ShopifyCreateCustomerNode)->execute($run, ['first_name' => 'Ada'], []))->toThrow(InvalidArgumentException::class, 'email address or a phone number');
    });

    it('creates a product with one variant from a price and SKU', function () {
        fakeShopify(['POST /products.json' => ['product' => ['id' => 5]]]);
        $run = shopifyRun();

        (new ShopifyCreateProductNode)->execute($run, ['title' => 'Cotton t-shirt', 'vendor' => 'Acme', 'status' => 'draft', 'price' => '19.99', 'sku' => 'TS-1', 'tags' => 'summer, cotton'], []);

        expect(shopifySent('POST /products.json'))->toBe(['product' => [
            'title' => 'Cotton t-shirt', 'vendor' => 'Acme', 'status' => 'draft', 'tags' => 'summer, cotton', 'variants' => [['price' => '19.99', 'sku' => 'TS-1']],
        ]])->and(fn () => (new ShopifyCreateProductNode)->execute($run, ['price' => '1'], []))->toThrow(InvalidArgumentException::class, 'title')
            ->and(fn () => (new ShopifyCreateProductNode)->execute($run, ['title' => 'x', 'price' => 'a lot'], []))->toThrow(InvalidArgumentException::class, 'amount such as');
    });

    it('updates a variant\'s price, and wants something to change', function () {
        fakeShopify(['PUT /variants/808950810.json' => ['variant' => ['id' => 808950810]]]);
        $run = shopifyRun();

        (new ShopifyUpdateVariantNode)->execute($run, ['variant_id' => '808950810', 'price' => '24.50', 'compare_at_price' => '30'], []);

        expect(shopifySent('PUT /variants/808950810.json'))->toBe(['variant' => ['id' => 808950810, 'price' => '24.50', 'compare_at_price' => '30']])
            ->and(fn () => (new ShopifyUpdateVariantNode)->execute($run, ['variant_id' => '808950810'], []))->toThrow(InvalidArgumentException::class, 'something to change');
    });

    it('sets and adjusts stock with whole numbers', function () {
        fakeShopify(['POST /inventory_levels/set.json' => ['inventory_level' => ['available' => 5]], 'POST /inventory_levels/adjust.json' => ['inventory_level' => ['available' => 3]]]);
        $run = shopifyRun();

        (new ShopifySetInventoryLevelNode)->execute($run, ['inventory_item_id' => '808950810', 'location_id' => '905684977', 'available' => '5'], []);
        (new ShopifyAdjustInventoryLevelNode)->execute($run, ['inventory_item_id' => '808950810', 'location_id' => '905684977', 'adjustment' => -2], []);

        expect(shopifySent('POST /inventory_levels/set.json'))->toBe(['location_id' => 905684977, 'inventory_item_id' => 808950810, 'available' => 5])
            ->and(shopifySent('POST /inventory_levels/adjust.json'))->toBe(['location_id' => 905684977, 'inventory_item_id' => 808950810, 'available_adjustment' => -2])
            ->and(fn () => (new ShopifySetInventoryLevelNode)->execute($run, ['inventory_item_id' => '1', 'location_id' => '2', 'available' => -1], []))->toThrow(InvalidArgumentException::class, 'zero or more')
            ->and(fn () => (new ShopifyAdjustInventoryLevelNode)->execute($run, ['inventory_item_id' => '1', 'location_id' => '2', 'adjustment' => 0], []))->toThrow(InvalidArgumentException::class, 'other than zero');
    });

    it('completes a draft order with the payment pending by default', function () {
        fakeShopify(['PUT /draft_orders/994118539/complete.json' => ['draft_order' => ['id' => 994118539]]]);

        (new ShopifyCompleteDraftOrderNode)->execute(shopifyRun(), ['draft_order_id' => '994118539'], []);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/draft_orders/994118539/complete.json') && str_contains($request->url(), 'payment_pending=true'));
    });

    it('only makes https webhooks for a topic that looks like one', function () {
        fakeShopify(['POST /webhooks.json' => ['webhook' => ['id' => 1]]]);
        $run = shopifyRun();
        $node = new ShopifyCreateWebhookNode;

        $node->execute($run, ['topic' => 'orders/create', 'address' => 'https://example.com/hook'], []);

        expect(shopifySent('POST /webhooks.json'))->toBe(['webhook' => ['topic' => 'orders/create', 'address' => 'https://example.com/hook', 'format' => 'json']])
            ->and(fn () => $node->execute($run, ['topic' => 'orders create', 'address' => 'https://example.com'], []))->toThrow(InvalidArgumentException::class, 'orders/create')
            ->and(fn () => $node->execute($run, ['topic' => 'orders/create', 'address' => 'http://example.com'], []))->toThrow(InvalidArgumentException::class, 'https');
    });

    it('turns a Shopify error into a sentence, with a hint for a refused token, a missing scope or a limit', function () {
        $run = shopifyRun();

        fakeShopify(['GET /orders/1.json' => fn () => Http::response(['errors' => 'Not Found'], 404)]);
        expect(fn () => (new ShopifyGetOrderNode)->execute($run, ['order_id' => '1'], []))->toThrow(RuntimeException::class, 'Shopify error: Not Found.');

        fakeShopify(['GET /orders/1.json' => fn () => Http::response(['errors' => '[API] Invalid API key or access token'], 401)]);
        expect(fn () => (new ShopifyGetOrderNode)->execute($run, ['order_id' => '1'], []))->toThrow(RuntimeException::class, 'Reconnect Shopify');

        fakeShopify(['GET /orders/1.json' => fn () => Http::response(['errors' => 'This action requires merchant approval for read_orders scope.'], 403)]);
        expect(fn () => (new ShopifyGetOrderNode)->execute($run, ['order_id' => '1'], []))->toThrow(RuntimeException::class, 'access scopes');

        fakeShopify(['GET /orders/1.json' => fn () => Http::response(['errors' => 'Exceeded 2 calls per second'], 429)]);
        expect(fn () => (new ShopifyGetOrderNode)->execute($run, ['order_id' => '1'], []))->toThrow(RuntimeException::class, 'rate limiting');

        fakeShopify(['POST /customers.json' => fn () => Http::response(['errors' => ['email' => ['has already been taken'], 'phone' => ['is invalid']]], 422)]);
        expect(fn () => (new ShopifyCreateCustomerNode)->execute($run, ['email' => 'ada@example.com'], []))->toThrow(RuntimeException::class, 'email has already been taken; phone is invalid');
    });
});

describe('shopify dropdowns', function () {
    function shopifyOptionsQuery(?string $search = null, ?string $cursor = null, array $data = ['shop' => SHOPIFY_SHOP]): NodeOptionsQuery
    {
        return new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), [], SHOPIFY_TOKEN, $search, $cursor, $data);
    }

    it('lists active locations with where they are', function () {
        fakeShopify(['GET /locations.json' => ['locations' => [['id' => 1, 'name' => 'Warehouse', 'active' => true, 'city' => 'Leeds', 'country_name' => 'UK'], ['id' => 2, 'name' => 'Old shop', 'active' => false]]]]);

        expect(app(ShopifyOptions::class)->load('shopify.locations', shopifyOptionsQuery())->toArray())->toBe(['options' => [['value' => '1', 'label' => 'Warehouse', 'description' => 'Leeds, UK']], 'next_cursor' => null]);
    });

    it('lists products and goes on with the next page', function () {
        fakeShopify(['GET /products.json' => fn () => Http::response(['products' => [['id' => 10, 'title' => 'T-shirt']]], 200, ['Link' => '<https://acme.myshopify.com/x.json?page_info=NEXT1>; rel="next"'])]);

        $page = app(ShopifyOptions::class)->load('shopify.products', shopifyOptionsQuery());

        expect($page->toArray())->toBe(['options' => [['value' => '10', 'label' => 'T-shirt', 'description' => '10']], 'next_cursor' => 'NEXT1']);

        fakeShopify(['GET /products.json' => ['products' => []]]);
        app(ShopifyOptions::class)->load('shopify.products', shopifyOptionsQuery(cursor: 'NEXT1'));

        expect(shopifySent('GET /products.json'))->toBe(['page_info' => 'NEXT1', 'limit' => '50']);
    });

    it('searches customers on Shopify and shows a name, or the email when there is none', function () {
        fakeShopify(['GET /customers/search.json' => ['customers' => [['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com'], ['id' => 2, 'first_name' => null, 'last_name' => null, 'email' => 'bob@example.com']]]]);

        $page = app(ShopifyOptions::class)->load('shopify.customers', shopifyOptionsQuery(search: 'ada'));

        expect(shopifySent('GET /customers/search.json'))->toMatchArray(['query' => 'ada'])
            ->and(array_column($page->toArray()['options'], 'label'))->toBe(['Ada Lovelace', 'bob@example.com']);
    });

    it('won\'t call a store that isn\'t a myshopify.com address', function () {
        fakeShopify([]);

        expect(fn () => app(ShopifyOptions::class)->load('shopify.locations', shopifyOptionsQuery(data: ['shop' => 'evil.test'])))->toThrow(ConnectorException::class, 'store address');
        Http::assertNothingSent();
    });

    it('is offered by the nodes that name a customer, a product or a location', function () {
        $registry = app(NodeRegistry::class);

        expect($registry->resolve('shopify_get_customer')->configSchema()['properties']['customer_id']['x-options']['source'])->toBe('shopify.customers')
            ->and($registry->resolve('shopify_get_product')->configSchema()['properties']['product_id']['x-options']['source'])->toBe('shopify.products')
            ->and($registry->resolve('shopify_set_inventory_level')->configSchema()['properties']['location_id']['x-options']['source'])->toBe('shopify.locations');
    });
});

/**
 * A published workflow whose first step is a Shopify reader switched on as a
 * trigger, with its subscription synced against a faked Shopify.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 * @param  array<string, mixed>  $data
 */
function shopifyTrigger(string $nodeType = 'shopify_list_orders', array $nodeConfig = [], array $routes = [], bool $sync = true, array $data = []): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeShopify($routes + ['POST /webhooks.json' => ['webhook' => ['id' => 4759306]]]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'shopify') ?? Connector::factory()->create(['key' => 'shopify']))
        ->create(['data' => ['shop' => SHOPIFY_SHOP, 'api_key' => SHOPIFY_TOKEN, 'api_secret' => SHOPIFY_SECRET, ...$data], 'created_by' => $owner->id]);

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

/**
 * A delivery as Shopify sends it, signed with the app's API secret.
 */
function shopifyDelivery(string $secret = SHOPIFY_SECRET, string $topic = 'orders/create', string $shop = SHOPIFY_SHOP, string $eventId = 'evt-1', bool $tamper = false)
{
    $body = json_encode(shopifyOrder(1001));

    return test()->call('POST', '/api/hooks/apps/shopify', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode(hash_hmac('sha256', $body, $secret, true)),
        'HTTP_X_SHOPIFY_TOPIC' => $topic,
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shop,
        'HTTP_X_SHOPIFY_EVENT_ID' => $eventId,
    ], $tamper ? $body.' ' : $body);
}

describe('shopify triggers', function () {
    it('starts one run for each new order, once, after a baseline', function () {
        $trigger = shopifyTrigger(sync: false);
        $poll = fn (array $orders) => fakeShopify(['GET /orders.json' => ['orders' => $orders]]);

        $poll([shopifyOrder(1)]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([shopifyOrder(3, ['created_at' => '2026-03-03T00:00:00+00:00']), shopifyOrder(2, ['created_at' => '2026-03-02T00:00:00+00:00']), shopifyOrder(1)]);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        dispatch_sync(new PollTrigger($trigger->fresh()));

        $events = $trigger->events()->where('status', 'queued')->get();

        expect($events->pluck('payload.id')->sort()->values()->all())->toBe([2, 3])
            ->and($events->first()->payload['line_items'][0]['title'])->toBe('T-shirt');
    });

    it('starts a run once when an order is paid, and not again when the paid order is edited', function () {
        $trigger = shopifyTrigger('shopify_order_paid', sync: false);
        $poll = fn (array $orders) => fakeShopify(['GET /orders.json' => ['orders' => $orders]]);

        $poll([]);
        dispatch_sync(new PollTrigger($trigger));
        $poll([shopifyOrder(7)]);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        $poll([shopifyOrder(7, ['updated_at' => '2026-03-02T09:00:00+00:00', 'note' => 'edited'])]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->count())->toBe(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'financial_status=paid') && str_contains($request->url(), 'updated_at_min='));
    });

    it('starts a run each time an order changes', function () {
        $trigger = shopifyTrigger('shopify_order_updated', sync: false);
        $poll = fn (array $orders) => fakeShopify(['GET /orders.json' => ['orders' => $orders]]);

        $poll([]);
        dispatch_sync(new PollTrigger($trigger));
        $poll([shopifyOrder(7)]);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        $poll([shopifyOrder(7, ['updated_at' => '2026-03-02T09:00:00+00:00'])]);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->count())->toBe(2);
    });

    it('registers one webhook per topic, signed with the app\'s secret, and shares it between triggers', function () {
        $trigger = shopifyTrigger();
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('topic:orders/create')
            ->and($watch->external_id)->toBe('4759306')
            ->and($watch->secret)->toBe(SHOPIFY_SECRET)
            ->and($watch->meta)->toMatchArray(['shop' => SHOPIFY_SHOP, 'topic' => 'orders/create'])
            ->and($trigger->subscription->routing_key)->toBe('acme.myshopify.com|orders/create')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(shopifySent('POST /webhooks.json'))->toBe(['webhook' => ['topic' => 'orders/create', 'address' => url('/api/hooks/apps/shopify'), 'format' => 'json']]);

        $source = app(ShopifySource::class);
        $same = new Trigger(['config' => ['node_type' => 'shopify_list_orders']]);
        $other = new Trigger(['config' => ['node_type' => 'shopify_order_paid']]);

        expect($source->watchKeyFor($same))->toBe('topic:orders/create')
            ->and($source->watchKeyFor($other))->toBe('topic:orders/paid');
    });

    it('listens to the topic each trigger is about', function () {
        $topics = [
            'shopify_list_orders' => 'orders/create', 'shopify_order_paid' => 'orders/paid', 'shopify_order_cancelled' => 'orders/cancelled', 'shopify_order_updated' => 'orders/updated',
            'shopify_list_customers' => 'customers/create', 'shopify_customer_updated' => 'customers/update', 'shopify_list_products' => 'products/create', 'shopify_product_updated' => 'products/update',
        ];

        foreach ($topics as $type => $topic) {
            expect(app(NodeRegistry::class)->resolve($type)->webhookTopic())->toBe($topic);
        }

        expect(app(NodeRegistry::class)->resolve('shopify_list_abandoned_checkouts')->webhookTopic())->toBeNull();
    });

    it('keeps polling a trigger Shopify has no webhook for', function () {
        $trigger = shopifyTrigger('shopify_list_abandoned_checkouts');

        expect($trigger->subscription)->toBeNull()->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('starts a poll when a signed delivery arrives, and only once for the same event', function () {
        shopifyTrigger();

        shopifyDelivery(eventId: 'evt-9')->assertOk();
        shopifyDelivery(eventId: 'evt-9')->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
        expect(ProviderEvent::query()->where('provider', 'shopify')->sole())->event_id->toBe('evt-9')->routing_key->toBe('acme.myshopify.com|orders/create')->event_type->toBe('orders/create');

        shopifyDelivery(eventId: 'evt-10')->assertOk();

        Queue::assertPushed(PollTrigger::class, 2);
    });

    it('refuses a wrong secret, an altered body, another store, another topic and missing headers', function () {
        shopifyTrigger();

        shopifyDelivery('not-the-secret')->assertUnauthorized();
        shopifyDelivery(tamper: true)->assertUnauthorized();
        shopifyDelivery(shop: 'other.myshopify.com')->assertUnauthorized();
        shopifyDelivery(topic: 'orders/paid')->assertUnauthorized();
        test()->postJson('/api/hooks/apps/shopify', shopifyOrder())->assertUnauthorized();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('is closed while the switch is off', function () {
        shopifyTrigger();
        config(['triggers.push.shopify.enabled' => false]);

        shopifyDelivery()->assertNotFound();
    });

    it('keeps polling and says what to add when the connection has no API secret', function () {
        $trigger = shopifyTrigger(data: ['api_secret' => '']);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)
            ->and($trigger->subscription->last_error)->toContain('API secret key')
            ->and(ProviderWatch::query()->count())->toBe(0);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    });

    it('takes over the webhook that already points here, and reports one Shopify refuses', function () {
        $taken = fn () => Http::response(['errors' => ['address' => ['for this topic has already been taken']]], 422);

        $adopted = shopifyTrigger(routes: ['POST /webhooks.json' => $taken, 'GET /webhooks.json' => ['webhooks' => [['id' => 555, 'topic' => 'orders/create']]]]);
        expect($adopted->subscription->status)->toBe(SubscriptionStatus::Active)->and(ProviderWatch::query()->sole()->external_id)->toBe('555');

        $refused = shopifyTrigger(routes: ['POST /webhooks.json' => $taken, 'GET /webhooks.json' => ['webhooks' => []]]);
        expect($refused->subscription->status)->toBe(SubscriptionStatus::Failed)->and($refused->subscription->last_error)->toContain('already been taken');
    });

    it('says when Shopify refuses the token', function () {
        $trigger = shopifyTrigger(routes: ['POST /webhooks.json' => fn () => Http::response(['errors' => 'Invalid API key or access token'], 401)]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::ReconnectNeeded);
    });

    it('makes a dropped webhook again, fixes its address, and removes it', function () {
        shopifyTrigger();
        $watch = ProviderWatch::query()->sole();
        $source = app(ShopifySource::class);

        fakeShopify(['GET /webhooks/4759306.json' => ['webhook' => ['id' => 4759306, 'address' => 'https://old.example.com/api/hooks/apps/shopify']], 'PUT /webhooks/4759306.json' => ['webhook' => ['id' => 4759306]]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect(shopifySent('PUT /webhooks/4759306.json'))->toBe(['webhook' => ['id' => 4759306, 'address' => url('/api/hooks/apps/shopify')]]);

        fakeShopify(['GET /webhooks/4759306.json' => fn () => Http::response(['errors' => 'Not Found'], 404), 'POST /webhooks.json' => ['webhook' => ['id' => 777]]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh())->status->toBe(SubscriptionStatus::Active)->external_id->toBe('777')->secret->toBe(SHOPIFY_SECRET);

        fakeShopify(['DELETE /webhooks/777.json' => []]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE');
        expect(ProviderWatch::query()->count())->toBe(0);
    });

    it('has the paid trigger read paid orders by when they changed', function () {
        $node = new ShopifyOrderPaidNode;

        expect($node->triggerItemId(shopifyOrder(7)))->toBe('7')
            ->and((new ShopifyListOrdersNode)->triggerItemId(shopifyOrder(7)))->toBe('7')
            ->and($node->configSchema()['properties'])->not->toHaveKey('financial_status')
            ->and($node->triggerName())->toBe('Shopify: Order paid');
    });
});

describe('shopify connection', function () {
    it('refuses a store address that isn\'t a myshopify.com one without calling, and reads the shop name for one that is', function () {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $connector = Connector::query()->firstWhere('key', 'shopify') ?? Connector::factory()->create(['key' => 'shopify', 'name' => 'Shopify']);
        $credential = fn (array $data): ConnectorCredential => ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)->create(['data' => ['api_key' => SHOPIFY_TOKEN, ...$data], 'created_by' => $owner->id]);

        fakeShopify([]);
        $bad = app(ConnectorCredentialTester::class)->test($credential(['shop' => 'evil.test']));
        expect($bad['ok'])->toBeFalse()->and($bad['message'])->toContain('myshopify');
        Http::assertNothingSent();

        fakeShopify(['GET /shop.json' => ['shop' => ['name' => 'Acme Store']]]);
        $good = app(ConnectorCredentialTester::class)->test($credential(['shop' => 'acme']));
        expect($good['ok'])->toBeTrue()->and($good['account'])->toBe('Acme Store');

        fakeShopify(['GET /shop.json' => fn () => Http::response(['errors' => '[API] Invalid API key or access token'], 401)]);
        expect(app(ConnectorCredentialTester::class)->test($credential(['shop' => 'acme']))['ok'])->toBeFalse();
    });
});
