<?php

namespace App\Actions\Billing;

use App\Models\Billing\Plan;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\UsagePeriods;

/**
 * Brings the workspace's current usage period in line with a subscription
 * Stripe just reported (created, renewed, changed plan, trialing, past
 * due). A new billing cycle closes the old period and opens a new one with
 * a fresh allowance; a plan change within the cycle resizes the allowance in
 * place, so credits already used this cycle still count. See `UsagePeriods`.
 *
 * Purchased credits are never touched: they live in the workspace's wallet
 * as carry-over grants, not in the period.
 */
class OpenUsagePeriodForSubscriptionAction
{
    public function __construct(private readonly UsagePeriods $periods) {}

    public function execute(Workspace $workspace, ?Plan $plan, ?string $stripeSubscriptionId): UsagePeriod
    {
        // An unrecognised Stripe price leaves `$plan` null; falling back to
        // the default plan keeps the period metered instead of handing the
        // workspace an unlimited `credits_limit`.
        $plan ??= Plan::default();

        $subscription = $stripeSubscriptionId !== null
            ? $workspace->subscriptions()->where('stripe_id', $stripeSubscriptionId)->first()
            : null;

        return $this->periods->syncSubscription($workspace, $plan, $subscription);
    }
}
