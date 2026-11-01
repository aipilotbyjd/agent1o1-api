<?php

namespace App\Actions\Billing;

use App\Exceptions\BillingAccountNotFoundException;
use App\Models\Workspaces\Workspace;

/**
 * Opens a Stripe-hosted Billing Portal session, where a customer manages the
 * things this API deliberately doesn't reimplement: payment methods, invoice
 * history and receipts, and cancelling a subscription. Stripe owns those
 * screens, keeps them PCI-compliant, and localises them: rebuilding card
 * capture here would mean handling card data we currently never touch.
 *
 * Requires the Customer Portal to be configured once in the Stripe dashboard
 * (Settings → Billing → Customer portal); without it Stripe rejects the
 * session-create call.
 *
 * The return URL is built here rather than accepted from the caller: the
 * same rule the checkout actions follow. Stripe sends the customer's browser
 * wherever this points, so taking it from the request would turn the endpoint
 * into an open redirect.
 */
class CreateBillingPortalSessionAction
{
    /**
     * @throws BillingAccountNotFoundException
     */
    public function execute(Workspace $workspace): string
    {
        // Cashier's own `billingPortalUrl()` asserts this and throws
        // `InvalidCustomer`, which would surface as a 500. A Free workspace
        // that never reached checkout is an ordinary state, not a fault.
        if (! $workspace->hasStripeId()) {
            throw new BillingAccountNotFoundException;
        }

        return $workspace->billingPortalUrl($this->returnUrl($workspace));
    }

    private function returnUrl(Workspace $workspace): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/workspaces/{$workspace->slug}/billing";
    }
}
