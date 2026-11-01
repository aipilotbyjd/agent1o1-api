<?php

use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;
use Stripe\BillingPortal\Session as StripePortalSession;
use Stripe\StripeClient;

/**
 * The Stripe-hosted Billing Portal, where a customer manages what this API
 * deliberately doesn't reimplement: payment methods, receipts, and cancelling.
 *
 * The parameters Stripe is called with matter more than the URL that comes
 * back, so the stub records them: `return_url` is built server-side from
 * config, and taking it from the request instead would make this endpoint an
 * open redirect.
 */
function stubStripeBillingPortal(string $url = 'https://billing.stripe.com/session/test'): object
{
    $sessions = new class($url)
    {
        /** @var array<string, mixed>|null */
        public ?array $received = null;

        public function __construct(private string $url) {}

        public function create(array $params = []): StripePortalSession
        {
            $this->received = $params;

            return StripePortalSession::constructFrom(['url' => $this->url]);
        }
    };

    $billingPortal = new class($sessions)
    {
        public function __construct(public object $sessions) {}
    };

    app()->bind(StripeClient::class, fn (): object => new class($billingPortal)
    {
        public function __construct(public object $billingPortal) {}
    });

    return $sessions;
}

function portalWorkspace(?string $stripeId = 'cus_acme'): Workspace
{
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

    if ($stripeId !== null) {
        $workspace->forceFill(['stripe_id' => $stripeId])->save();
    }

    Passport::actingAs($workspace->owner);

    return $workspace;
}

it('returns a stripe billing portal url', function () {
    $workspace = portalWorkspace();
    stubStripeBillingPortal();

    $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/portal")
        ->assertOk()
        ->assertJsonPath('data.portal_url', 'https://billing.stripe.com/session/test');
});

it('opens the portal for the workspace stripe customer', function () {
    $workspace = portalWorkspace();
    $sessions = stubStripeBillingPortal();

    $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/portal")->assertOk();

    expect($sessions->received['customer'])->toBe('cus_acme');
});

/**
 * Stripe sends the customer's browser wherever `return_url` points, so it is
 * built from `config('app.frontend_url')` and the workspace slug (the same
 * rule the checkout actions follow) and never read from the request.
 */
it('builds the return url server-side from config', function () {
    config(['app.frontend_url' => 'https://app.example.com/']);

    $workspace = portalWorkspace();
    $sessions = stubStripeBillingPortal();

    $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/portal")->assertOk();

    expect($sessions->received['return_url'])
        ->toBe("https://app.example.com/workspaces/{$workspace->slug}/billing");
});

it('ignores a return url supplied by the caller', function () {
    config(['app.frontend_url' => 'https://app.example.com']);

    $workspace = portalWorkspace();
    $sessions = stubStripeBillingPortal();

    $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/portal", [
        'return_url' => 'https://evil.example.com/steal',
    ])->assertOk();

    expect($sessions->received['return_url'])->not->toContain('evil.example.com');
});

/**
 * A Free workspace that never reached checkout has no Stripe customer.
 * Cashier's `billingPortalUrl()` throws `InvalidCustomer` for that, which
 * would surface as a 500: it's an ordinary state, so it answers 409 and the
 * frontend can send them to checkout instead.
 */
it('409s for a workspace that has never been to checkout', function () {
    $workspace = portalWorkspace(stripeId: null);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/portal")
        ->assertStatus(409);
});

it('does not call stripe for a workspace with no billing account', function () {
    $workspace = portalWorkspace(stripeId: null);
    $sessions = stubStripeBillingPortal();

    $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/portal")->assertStatus(409);

    expect($sessions->received)->toBeNull();
});

/**
 * `BillingManage`, not `BillingView`: the portal can change the payment
 * method and cancel the subscription. Every role down to Viewer can *see*
 * billing; only admins and owners can act on it.
 */
it('denies an editor, who can view billing but not manage it', function () {
    $workspace = portalWorkspace();
    stubStripeBillingPortal();

    $editor = User::factory()->create();
    $workspace->members()->create([
        'user_id' => $editor->id,
        'role' => Role::Editor,
        'joined_at' => now(),
    ]);

    Passport::actingAs($editor);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/portal")
        ->assertForbidden();

    // The same editor can still read the billing overview.
    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing")->assertOk();
});

it('denies a non-member entirely', function () {
    $workspace = portalWorkspace();
    stubStripeBillingPortal();

    Passport::actingAs(User::factory()->create());

    $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/portal")
        ->assertForbidden();
});
