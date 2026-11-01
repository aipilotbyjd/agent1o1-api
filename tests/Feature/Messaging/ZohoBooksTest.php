<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\ZohoBooks\AbstractZohoBooksNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksCreateBillNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksCreateCustomerNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksCreateInvoiceNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksDeleteInvoiceNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksEmailInvoiceNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksGetInvoiceNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksListInvoicesNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksListNewCustomersNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksListNewInvoicesNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksMarkInvoiceNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksRecordPaymentNode;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ZohoBooksOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const ZBOOKS_ID = '460000000012345';

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function zbooksData(array $data = []): array
{
    return ['region' => 'us', 'client_id' => 'cid', 'api_key' => 'csecret', 'refresh_token' => 'rtoken', 'organization_id' => '6001', ...$data];
}

/**
 * A run with a connected Zoho Books account in its workspace.
 *
 * @param  array<string, mixed>  $data
 */
function zbooksRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'zoho_books') ?? Connector::factory()->create(['key' => 'zoho_books']))->id,
        'data' => zbooksData($data),
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Zoho Books and the Zoho login. A key is "METHOD /path" under /books/v3;
 * the value is the JSON body, or a closure returning a response. A route nobody
 * answers is a 404, so a node that calls something unexpected fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeZohoBooks(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['*zoho*' => function (Request $request) use ($routes) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/oauth/v2/token') {
            return Http::response(['access_token' => '1000.zoho-session', 'api_domain' => 'https://www.zohoapis.com', 'token_type' => 'Bearer', 'expires_in' => 3600]);
        }

        $key = $request->method().' '.preg_replace('~^/books/v3~', '', $path);
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['code' => 5, 'message' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : Http::response(['code' => 0, 'message' => 'success', ...(array) $answer]);
    }]);
}

function zbooksRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/books/v3~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function zbooksBody(string $route): array
{
    return (array) json_decode(zbooksRequest($route)->body(), true);
}

/**
 * @return array<string, string>
 */
function zbooksQuery(string $route): array
{
    parse_str((string) parse_url(zbooksRequest($route)->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function zbooksInvoice(string $id = ZBOOKS_ID, array $extra = []): array
{
    return ['invoice_id' => $id, 'invoice_number' => 'INV-000001', 'customer_id' => '460000000000111', 'customer_name' => 'Acme Inc', 'status' => 'draft', 'date' => '2026-03-01', 'due_date' => '2026-03-31', 'total' => 240, 'balance' => 240, 'currency_code' => 'USD', 'created_time' => now()->subMinutes(10)->utc()->format('Y-m-d\TH:i:sO'), ...$extra];
}

describe('zoho books nodes', function () {
    it('registers the nodes under the zoho_books connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'zoho_books_'));

        expect(count($types))->toBeGreaterThanOrEqual(26);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractZohoBooksNode::class)->and($registry->resolve($type)->category())->toBe('zoho_books');
        }
    });

    it('creates a customer with the token in a header and the organization in the query', function () {
        fakeZohoBooks(['POST /contacts' => ['contact' => ['contact_id' => ZBOOKS_ID, 'contact_name' => 'Acme Inc', 'contact_type' => 'customer']]]);

        $output = (new ZohoBooksCreateCustomerNode)->execute(zbooksRun(), ['name' => ' Acme Inc ', 'email' => 'Billing@Acme.com', 'first_name' => 'Ada'], []);

        $request = zbooksRequest('POST /contacts');

        expect($output['id'])->toBe(ZBOOKS_ID)
            ->and($request->url())->toStartWith('https://www.zohoapis.com/books/v3/contacts')
            ->and($request->header('Authorization')[0])->toBe('Zoho-oauthtoken 1000.zoho-session')
            ->and(zbooksQuery('POST /contacts'))->toMatchArray(['organization_id' => '6001'])
            ->and(zbooksBody('POST /contacts'))->toMatchArray(['contact_name' => 'Acme Inc', 'contact_type' => 'customer', 'contact_persons' => [['first_name' => 'Ada', 'email' => 'billing@acme.com', 'is_primary_contact' => true]]]);
    });

    it('refuses a connection without the organization id, and a bad id', function () {
        fakeZohoBooks([]);

        expect(fn () => (new ZohoBooksGetInvoiceNode)->execute(zbooksRun(['organization_id' => '']), ['invoice_id' => ZBOOKS_ID], []))->toThrow(InvalidArgumentException::class, 'organization id')
            ->and(fn () => (new ZohoBooksGetInvoiceNode)->execute(zbooksRun(), ['invoice_id' => '../x'], []))->toThrow(InvalidArgumentException::class);
    });

    it('creates an invoice from lines, with the send option only when asked', function () {
        fakeZohoBooks(['POST /invoices' => ['invoice' => zbooksInvoice()]]);

        $output = (new ZohoBooksCreateInvoiceNode)->execute(zbooksRun(), ['customer_id' => '460000000000111', 'line_items' => '[{"name": "Consulting", "rate": 120, "quantity": 2}]', 'due_date' => '2026-03-31T10:00:00Z', 'reference' => 'PO-7'], []);

        expect($output)->toMatchArray(['id' => ZBOOKS_ID, 'number' => 'INV-000001', 'customer' => 'Acme Inc'])
            ->and(zbooksBody('POST /invoices'))->toMatchArray(['customer_id' => '460000000000111', 'due_date' => '2026-03-31', 'reference_number' => 'PO-7', 'line_items' => [['name' => 'Consulting', 'rate' => 120, 'quantity' => 2]]])
            ->and(zbooksQuery('POST /invoices'))->not->toHaveKey('send');
    });

    it('needs lines that name an item, and says what a line looks like', function () {
        fakeZohoBooks([]);

        expect(fn () => (new ZohoBooksCreateInvoiceNode)->execute(zbooksRun(), ['customer_id' => ZBOOKS_ID, 'line_items' => '[]'], []))->toThrow(InvalidArgumentException::class, 'JSON list')
            ->and(fn () => (new ZohoBooksCreateInvoiceNode)->execute(zbooksRun(), ['customer_id' => ZBOOKS_ID, 'line_items' => [['rate' => 5]]], []))->toThrow(InvalidArgumentException::class, 'item_id');
    });

    it('says a missing invoice is not found, and shows the lines of one that is', function () {
        fakeZohoBooks(['GET /invoices/'.ZBOOKS_ID => fn () => Http::response(['code' => 1002, 'message' => 'Invoice does not exist.'], 404)]);
        $missing = (new ZohoBooksGetInvoiceNode)->execute(zbooksRun(), ['invoice_id' => ZBOOKS_ID], []);

        fakeZohoBooks(['GET /invoices/'.ZBOOKS_ID => ['invoice' => zbooksInvoice(extra: ['line_items' => [['item_id' => '1', 'name' => 'Consulting', 'rate' => 120, 'quantity' => 2, 'item_total' => 240]]])]]);
        $found = (new ZohoBooksGetInvoiceNode)->execute(zbooksRun(), ['invoice_id' => ZBOOKS_ID], []);

        expect($missing)->toBe(['found' => false, 'id' => ZBOOKS_ID])->and($found)->toMatchArray(['found' => true, 'lines' => [['item_id' => '1', 'name' => 'Consulting', 'description' => null, 'rate' => 120, 'quantity' => 2, 'total' => 240]]]);
    });

    it('lists invoices across pages, newest first, and stops when Books says there is no more', function () {
        $page = 0;
        fakeZohoBooks(['GET /invoices' => function () use (&$page) {
            $page++;

            return $page === 1
                ? ['invoices' => array_map(fn (int $n): array => zbooksInvoice((string) (460000000000000 + $n)), range(1, 200)), 'page_context' => ['has_more_page' => true]]
                : ['invoices' => [zbooksInvoice('460000000009999')], 'page_context' => ['has_more_page' => false]];
        }]);

        $output = (new ZohoBooksListInvoicesNode)->execute(zbooksRun(), ['limit' => 500, 'status' => 'overdue'], []);

        expect($output['count'])->toBe(201)->and($page)->toBe(2);
    });

    it('emails an invoice to the given addresses, and marks one sent or void', function () {
        fakeZohoBooks(['POST /invoices/'.ZBOOKS_ID.'/email' => [], 'POST /invoices/'.ZBOOKS_ID.'/status/void' => []]);

        $sent = (new ZohoBooksEmailInvoiceNode)->execute(zbooksRun(), ['invoice_id' => ZBOOKS_ID, 'to' => 'Ada@Acme.com, bob@acme.com', 'subject' => 'Your invoice'], []);
        $void = (new ZohoBooksMarkInvoiceNode)->execute(zbooksRun(), ['invoice_id' => ZBOOKS_ID, 'status' => 'void'], []);

        expect($sent)->toBe(['sent' => true, 'id' => ZBOOKS_ID, 'to' => ['ada@acme.com', 'bob@acme.com']])
            ->and(zbooksBody('POST /invoices/'.ZBOOKS_ID.'/email'))->toBe(['to_mail_ids' => ['ada@acme.com', 'bob@acme.com'], 'subject' => 'Your invoice'])
            ->and($void)->toBe(['id' => ZBOOKS_ID, 'status' => 'void']);
    });

    it('records a payment against an invoice and refuses a zero amount', function () {
        fakeZohoBooks(['POST /customerpayments' => ['payment' => ['payment_id' => '460000000077', 'amount' => 240]]]);

        $output = (new ZohoBooksRecordPaymentNode)->execute(zbooksRun(), ['customer_id' => '460000000000111', 'amount' => '240', 'invoice_id' => ZBOOKS_ID, 'date' => '2026-03-02'], []);

        expect($output['id'])->toBe('460000000077')
            ->and(zbooksBody('POST /customerpayments'))->toMatchArray(['customer_id' => '460000000000111', 'amount' => 240, 'payment_mode' => 'banktransfer', 'date' => '2026-03-02', 'invoices' => [['invoice_id' => ZBOOKS_ID, 'amount_applied' => 240]]])
            ->and(fn () => (new ZohoBooksRecordPaymentNode)->execute(zbooksRun(), ['customer_id' => '460000000000111', 'amount' => '0'], []))->toThrow(InvalidArgumentException::class);
    });

    it('creates a bill from account lines', function () {
        fakeZohoBooks(['POST /bills' => ['bill' => ['bill_id' => '460000000088', 'bill_number' => 'B-1', 'vendor_name' => 'Paper Co']]]);

        $output = (new ZohoBooksCreateBillNode)->execute(zbooksRun(), ['vendor_id' => '460000000000222', 'bill_number' => 'B-1', 'line_items' => '[{"account_id": "460000000012345", "rate": 40, "quantity": 5}]'], []);

        expect($output)->toMatchArray(['id' => '460000000088', 'number' => 'B-1'])->and(zbooksBody('POST /bills')['vendor_id'])->toBe('460000000000222');
    });

    it('deletes an invoice, and says so when it is already gone', function () {
        fakeZohoBooks(['DELETE /invoices/'.ZBOOKS_ID => []]);
        $gone = (new ZohoBooksDeleteInvoiceNode)->execute(zbooksRun(), ['invoice_id' => ZBOOKS_ID], []);

        fakeZohoBooks(['DELETE /invoices/'.ZBOOKS_ID => fn () => Http::response(['code' => 1002, 'message' => 'x'], 404)]);

        expect($gone)->toBe(['deleted' => true, 'id' => ZBOOKS_ID])->and((new ZohoBooksDeleteInvoiceNode)->execute(zbooksRun(), ['invoice_id' => ZBOOKS_ID], []))->toBe(['deleted' => false, 'id' => ZBOOKS_ID]);
    });

    it('explains a Books error with a hint', function () {
        fakeZohoBooks(['GET /invoices/'.ZBOOKS_ID => fn () => Http::response(['code' => 57, 'message' => 'You are not authorized to perform this operation'], 401)]);

        expect(fn () => (new ZohoBooksGetInvoiceNode)->execute(zbooksRun(), ['invoice_id' => ZBOOKS_ID], []))->toThrow(RuntimeException::class, 'Reconnect Zoho Books');
    });

    it('treats a 200 answer with an error code as an error', function () {
        fakeZohoBooks(['POST /items' => fn () => Http::response(['code' => 1001, 'message' => 'Item name already exists.'])]);

        expect(fn () => (new App\Nodes\Integrations\ZohoBooks\ZohoBooksCreateItemNode)->execute(zbooksRun(), ['name' => 'X', 'rate' => '5'], []))->toThrow(RuntimeException::class, 'already exists');
    });
});

describe('zoho books options', function () {
    it('lists customers by name a page at a time', function () {
        fakeZohoBooks(['GET /contacts' => ['contacts' => [['contact_id' => '1', 'contact_name' => 'Acme Inc', 'email' => 'a@acme.com']], 'page_context' => ['has_more_page' => true]]]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = new NodeOptionsQuery($workspace, User::factory()->create(), [], 'csecret', 'acm', credentialData: zbooksData());

        $page = app(ZohoBooksOptions::class)->load('zoho_books.customers', $query);

        expect($page->toArray()['options'])->toBe([['value' => '1', 'label' => 'Acme Inc', 'description' => 'a@acme.com']])
            ->and($page->toArray()['next_cursor'])->toBe('2')
            ->and(zbooksQuery('GET /contacts'))->toMatchArray(['contact_type' => 'customer', 'contact_name_contains' => 'acm', 'organization_id' => '6001']);
    });
});

/**
 * A published workflow whose first step is a Books reader switched on as a trigger.
 */
function zbooksTrigger(string $nodeType = 'zoho_books_list_new_invoices'): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeZohoBooks([]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'zoho_books') ?? Connector::factory()->create(['key' => 'zoho_books']))
        ->create(['data' => zbooksData(), 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
    SyncTriggerSubscription::dispatchSync($trigger->id);

    return $trigger->fresh();
}

describe('zoho books triggers', function () {
    it('polls new invoices: a baseline first, then one run for each', function () {
        $trigger = zbooksTrigger();
        $poll = fn (array $ids) => fakeZohoBooks(['GET /invoices' => ['invoices' => array_map(fn (string $id): array => zbooksInvoice($id), $ids), 'page_context' => ['has_more_page' => false]]]);

        $poll(['460000000000001']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['460000000000003', '460000000000002', '460000000000001']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['460000000000002', '460000000000003']);
    });

    it('drops old records and asks for customers only', function () {
        fakeZohoBooks(['GET /contacts' => ['contacts' => [['contact_id' => '1', 'created_time' => now()->subMinutes(5)->utc()->format('Y-m-d\TH:i:sO')], ['contact_id' => '2', 'created_time' => now()->subDays(9)->utc()->format('Y-m-d\TH:i:sO')]], 'page_context' => ['has_more_page' => false]]]);

        $output = (new ZohoBooksListNewCustomersNode)->execute(zbooksRun(), ['changed_within_minutes' => 60], []);

        expect(array_column($output['contacts'], 'id'))->toBe(['1'])->and(zbooksQuery('GET /contacts'))->toMatchArray(['contact_type' => 'customer', 'sort_column' => 'created_time', 'sort_order' => 'D']);
    });

    it('has no instant delivery of its own and says it checks every few minutes', function () {
        expect((new ZohoBooksListNewInvoicesNode)->triggerDescription())->toContain('every few minutes')->and(config('triggers.push.zoho_books'))->toBeNull();
    });
});
