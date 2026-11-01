<?php

use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;
use Stripe\Exception\InvalidRequestException;
use Stripe\Invoice as StripeInvoice;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

/**
 * `previewInvoice()` swaps nothing: it asks Stripe's Create Preview Invoice
 * API what a swap *would* charge, so the customer can see the prorated
 * amount before `checkout()` puts it through. Stubbing `StripeClient` keeps
 * these off the network the same way `InvoiceControllerTest` does; Cashier
 * additionally fetches the live subscription (`asStripeSubscription()`) to
 * work out which items a swap would delete, so the stub covers both
 * `subscriptions->retrieve` and `invoices->createPreview`.
 */
function stubStripeSwapPreview(string $subscriptionId, string $currentPriceId, ?StripeInvoice $preview = null): void
{
    $subscriptions = new class($subscriptionId, $currentPriceId)
    {
        public function __construct(private string $subscriptionId, private string $currentPriceId) {}

        public function retrieve(string $id, $params = null, $opts = null): StripeSubscription
        {
            if ($id !== $this->subscriptionId) {
                throw InvalidRequestException::factory("No such subscription: {$id}");
            }

            return StripeSubscription::constructFrom([
                'id' => $this->subscriptionId,
                'status' => 'active',
                'items' => [
                    'object' => 'list',
                    'data' => [[
                        'id' => 'si_current',
                        'quantity' => 1,
                        'price' => [
                            'id' => $this->currentPriceId,
                            'recurring' => ['usage_type' => 'licensed'],
                        ],
                    ]],
                ],
            ]);
        }
    };

    $invoices = new class($preview)
    {
        public function __construct(private ?StripeInvoice $preview) {}

        public function createPreview(array $params = []): StripeInvoice
        {
            if ($this->preview === null) {
                throw InvalidRequestException::factory('Nothing to invoice for customer.');
            }

            return $this->preview;
        }
    };

    app()->bind(StripeClient::class, fn (): object => new class($subscriptions, $invoices)
    {
        public function __construct(public object $subscriptions, public object $invoices) {}
    });
}

function stripePreviewInvoice(string $id, string $customer, array $overrides = []): StripeInvoice
{
    return StripeInvoice::constructFrom(array_merge([
        'id' => $id,
        'customer' => $customer,
        'number' => null,
        'status' => 'draft',
        'currency' => 'usd',
        'created' => 1700000000,
        'due_date' => null,
        'total' => 1500,
        'subtotal' => 1500,
        'tax' => 0,
        'amount_due' => 1500,
        'starting_balance' => 0,
        'ending_balance' => 0,
        'hosted_invoice_url' => null,
        'invoice_pdf' => null,
    ], $overrides));
}

function subscribedWorkspaceForSwap(Plan $plan, string $stripePriceId): Workspace
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->forceFill(['stripe_id' => 'cus_acme'])->save();

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'stripe_id' => 'sub_preview_1',
        'stripe_status' => 'active',
        'stripe_price' => $stripePriceId,
    ]);

    Passport::actingAs($owner);

    return $workspace;
}

it('previews the prorated invoice a plan swap would produce', function () {
    $current = Plan::factory()->create(['stripe_price_id_monthly' => 'price_current']);
    $target = Plan::factory()->create(['stripe_price_id_monthly' => 'price_target']);
    $workspace = subscribedWorkspaceForSwap($current, 'price_current');

    stubStripeSwapPreview('sub_preview_1', 'price_current', stripePreviewInvoice('in_preview', 'cus_acme'));

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/subscription/preview?plan_id={$target->id}&interval=monthly")
        ->assertOk()
        ->assertJsonPath('data.invoice.id', 'in_preview')
        ->assertJsonPath('data.invoice.total_raw', 1500);
});

it('returns a null preview when the workspace has no subscription to swap', function () {
    $target = Plan::factory()->create(['stripe_price_id_monthly' => 'price_target']);
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/subscription/preview?plan_id={$target->id}&interval=monthly")
        ->assertOk()
        ->assertJsonPath('data.invoice', null);
});

it('refuses to preview a swap onto a withdrawn interval', function () {
    $current = Plan::factory()->create(['stripe_price_id_monthly' => 'price_current']);
    $target = Plan::factory()->sellingOnly(['yearly'])->create();
    $workspace = subscribedWorkspaceForSwap($current, 'price_current');

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/subscription/preview?plan_id={$target->id}&interval=monthly")
        ->assertStatus(422);
});

it('refuses to preview a lifetime interval, which is not a subscription swap', function () {
    $current = Plan::factory()->create(['stripe_price_id_monthly' => 'price_current']);
    $target = Plan::factory()->soldForLifetime()->create();
    $workspace = subscribedWorkspaceForSwap($current, 'price_current');

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/subscription/preview?plan_id={$target->id}&interval=lifetime")
        ->assertStatus(422);
});

it('denies a non-member from previewing another workspace subscription swap', function () {
    $current = Plan::factory()->create(['stripe_price_id_monthly' => 'price_current']);
    $target = Plan::factory()->create(['stripe_price_id_monthly' => 'price_target']);
    $workspace = subscribedWorkspaceForSwap($current, 'price_current');

    Passport::actingAs(User::factory()->create());

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/subscription/preview?plan_id={$target->id}&interval=monthly")
        ->assertForbidden();
});
