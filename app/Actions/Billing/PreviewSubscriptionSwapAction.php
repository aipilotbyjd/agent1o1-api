<?php

namespace App\Actions\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\Workspaces\Workspace;
use Laravel\Cashier\Invoice;

class PreviewSubscriptionSwapAction
{
    private const SUBSCRIPTION_TYPE = 'default';

    /**
     * The prorated invoice a swap onto `$plan`/`$interval` would produce,
     * without charging anything: Stripe's Create Preview Invoice API, not a
     * local estimate. Null when there is nothing to swap: no active
     * subscription yet, which is `CheckoutSubscriptionAction`'s job instead.
     *
     * Guarded the same way as the swap itself, so a plan or interval that
     * checkout would refuse can't be previewed either.
     */
    public function execute(Workspace $workspace, Plan $plan, BillingInterval $interval): ?Invoice
    {
        abort_if(
            ! $interval->isRecurring(),
            422,
            "Interval [{$interval->value}] is not a recurring subscription.",
        );

        $unavailable = $plan->unavailableReasonFor($interval);

        abort_if($unavailable !== null, 422, $unavailable);

        $subscription = $workspace->subscription(self::SUBSCRIPTION_TYPE);

        if ($subscription === null) {
            return null;
        }

        return $subscription->previewInvoice($plan->stripePriceId($interval));
    }
}
