<?php

use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;
use Stripe\Collection as StripeCollection;
use Stripe\Exception\InvalidRequestException;
use Stripe\Invoice as StripeInvoice;
use Stripe\StripeClient;

/**
 * Billing history: what Stripe charged, as distinct from `CreditController`'s
 * ledger of what the workspace consumed. Invoices live in Stripe, not in our
 * database, so every one of these reads is a live API call in production.
 *
 * `Cashier::stripe()` resolves the client out of the container, which is the
 * seam these tests use: binding a stub means no test ever reaches Stripe (it
 * couldn't anyway: `cashier.secret` is empty in the test environment, and a
 * real resolution throws on the empty api key).
 */
function stubStripeInvoices(array $invoices = [], mixed $upcoming = null): void
{
    $service = new class($invoices, $upcoming)
    {
        public function __construct(private array $invoices, private mixed $upcoming) {}

        public function all(array $params = []): StripeCollection
        {
            // Cashier asks for perPage + 1 to decide whether more pages exist.
            $limit = $params['limit'] ?? 24;

            return StripeCollection::constructFrom([
                'object' => 'list',
                'data' => array_slice($this->invoices, 0, $limit),
                'has_more' => count($this->invoices) > $limit,
            ]);
        }

        public function retrieve(string $id, $params = null, $opts = null): StripeInvoice
        {
            foreach ($this->invoices as $invoice) {
                if ($invoice->id === $id) {
                    return $invoice;
                }
            }

            throw InvalidRequestException::factory("No such invoice: {$id}");
        }

        public function createPreview(array $params = []): StripeInvoice
        {
            if ($this->upcoming === null) {
                throw InvalidRequestException::factory('Nothing to invoice for customer.');
            }

            return $this->upcoming;
        }
    };

    app()->bind(StripeClient::class, fn (): object => new class($service)
    {
        public function __construct(public object $invoices) {}
    });
}

function stripeInvoice(string $id, string $customer, array $overrides = []): StripeInvoice
{
    return StripeInvoice::constructFrom(array_merge([
        'id' => $id,
        'customer' => $customer,
        'number' => strtoupper($id),
        'status' => 'paid',
        'currency' => 'usd',
        'created' => 1700000000,
        'due_date' => null,
        'total' => 2900,
        'subtotal' => 2900,
        'tax' => 0,
        'amount_due' => 2900,
        'starting_balance' => 0,
        'ending_balance' => 0,
        'hosted_invoice_url' => "https://invoice.stripe.com/{$id}",
        'invoice_pdf' => "https://invoice.stripe.com/{$id}.pdf",
    ], $overrides));
}

function billableWorkspace(?string $stripeId = 'cus_acme'): Workspace
{
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

    if ($stripeId !== null) {
        $workspace->forceFill(['stripe_id' => $stripeId])->save();
    }

    Passport::actingAs($workspace->owner);

    return $workspace;
}

/*
|--------------------------------------------------------------------------
| A workspace that never reached checkout
|--------------------------------------------------------------------------
|
| The common case: a Free workspace opening the billing screen. It has no
| Stripe customer at all, so these must answer "nothing yet" rather than error.
|
*/

it('returns an empty invoice list for a workspace with no stripe customer', function () {
    $workspace = billableWorkspace(stripeId: null);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/invoices")
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.has_more', false);
});

it('returns a null upcoming invoice for a workspace with no stripe customer', function () {
    $workspace = billableWorkspace(stripeId: null);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/invoices/upcoming")
        ->assertOk()
        ->assertJsonPath('data.invoice', null);
});

/*
|--------------------------------------------------------------------------
| Listing
|--------------------------------------------------------------------------
*/

it('lists invoices with money in both raw and formatted form', function () {
    $workspace = billableWorkspace();
    stubStripeInvoices([stripeInvoice('in_1', 'cus_acme')]);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/invoices")
        ->assertOk()
        ->assertJsonPath('data.0.id', 'in_1')
        ->assertJsonPath('data.0.number', 'IN_1')
        ->assertJsonPath('data.0.status', 'paid')
        ->assertJsonPath('data.0.currency', 'usd')
        ->assertJsonPath('data.0.total_raw', 2900)
        ->assertJsonPath('data.0.total', '$29.00')
        ->assertJsonPath('data.0.amount_due_raw', 2900)
        ->assertJsonPath('data.0.hosted_invoice_url', 'https://invoice.stripe.com/in_1')
        ->assertJsonPath('data.0.invoice_pdf', 'https://invoice.stripe.com/in_1.pdf');
});

/**
 * An unpaid invoice is exactly what a customer needs to see when a charge has
 * failed, so the list must not filter down to paid ones: Cashier's default
 * `invoices()` does, which is why the controller goes through the paginator.
 */
it('includes open invoices, not just paid ones', function () {
    $workspace = billableWorkspace();
    stubStripeInvoices([
        stripeInvoice('in_open', 'cus_acme', ['status' => 'open', 'amount_due' => 2900]),
        stripeInvoice('in_paid', 'cus_acme'),
    ]);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/invoices")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.status', 'open');
});

it('reports another page when stripe has more invoices than the page size', function () {
    $workspace = billableWorkspace();
    stubStripeInvoices([
        stripeInvoice('in_1', 'cus_acme'),
        stripeInvoice('in_2', 'cus_acme'),
        stripeInvoice('in_3', 'cus_acme'),
    ]);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/invoices?per_page=2");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.has_more', true);

    expect($response->json('meta.next_cursor'))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Single invoice
|--------------------------------------------------------------------------
*/

it('returns a single invoice', function () {
    $workspace = billableWorkspace();
    stubStripeInvoices([stripeInvoice('in_1', 'cus_acme')]);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/invoices/in_1")
        ->assertOk()
        ->assertJsonPath('data.invoice.id', 'in_1');
});

it('404s an invoice stripe does not have', function () {
    $workspace = billableWorkspace();
    stubStripeInvoices([]);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/invoices/in_missing")
        ->assertNotFound();
});

/**
 * The tenant-safety case. Cashier's `Invoice` constructor rejects a customer
 * mismatch; the endpoint collapses that into 404 rather than 403 so it never
 * confirms that another workspace's invoice id exists.
 */
it('404s an invoice belonging to another workspace', function () {
    $workspace = billableWorkspace();
    stubStripeInvoices([stripeInvoice('in_theirs', 'cus_someone_else')]);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/invoices/in_theirs")
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Upcoming
|--------------------------------------------------------------------------
*/

it('previews the next charge', function () {
    $workspace = billableWorkspace();
    stubStripeInvoices([], stripeInvoice('in_next', 'cus_acme', ['status' => 'draft', 'total' => 4900]));

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/invoices/upcoming")
        ->assertOk()
        ->assertJsonPath('data.invoice.id', 'in_next')
        ->assertJsonPath('data.invoice.total_raw', 4900);
});

it('returns null when stripe has nothing to preview', function () {
    $workspace = billableWorkspace();
    stubStripeInvoices([]);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/invoices/upcoming")
        ->assertOk()
        ->assertJsonPath('data.invoice', null);
});

/**
 * `upcoming` is registered before `{invoiceId}`; without that ordering the
 * literal would be looked up in Stripe as an invoice id and 404.
 */
it('does not treat the upcoming route as an invoice id', function () {
    $workspace = billableWorkspace();
    stubStripeInvoices([], stripeInvoice('in_next', 'cus_acme'));

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/invoices/upcoming")
        ->assertOk()
        ->assertJsonPath('data.invoice.id', 'in_next');
});

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

it('denies a non-member access to another workspace billing history', function () {
    $workspace = billableWorkspace();
    stubStripeInvoices([stripeInvoice('in_1', 'cus_acme')]);

    Passport::actingAs(User::factory()->create());

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/invoices")
        ->assertForbidden();
});
