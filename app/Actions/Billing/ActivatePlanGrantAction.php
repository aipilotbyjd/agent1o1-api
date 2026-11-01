<?php

namespace App\Actions\Billing;

use App\Enums\Billing\PlanGrantStatus;
use App\Models\Billing\PlanGrant;
use Illuminate\Support\Facades\DB;

/**
 * Turns a paid-for `PlanGrant` into live entitlement. Beyond flipping the
 * status this must re-open the usage period itself: a lifetime purchase
 * produces no `customer.subscription.created`, so nothing else would resize
 * `usage_periods.credits_limit` and the workspace would sit on the Free
 * allowance until the next month rolled over.
 *
 * The period is sized from `currentPlan()`, not the granted plan, so buying
 * a lifetime Starter while subscribed to Pro doesn't cut the allowance down
 * to Starter's: see `Workspace::currentPlan()`.
 *
 * Idempotent: safe to call again for the same grant (e.g. a redelivered
 * webhook), since an `Active` grant is a no-op.
 */
class ActivatePlanGrantAction
{
    public function __construct(private readonly OpenUsagePeriodForSubscriptionAction $openUsagePeriod) {}

    public function execute(PlanGrant $grant): void
    {
        if ($grant->status === PlanGrantStatus::Active) {
            return;
        }

        DB::transaction(function () use ($grant): void {
            $grant->update([
                'status' => PlanGrantStatus::Active,
                'granted_at' => now(),
            ]);

            $workspace = $grant->workspace;

            // Reads the grant we just wrote, so `currentPlan()` already
            // accounts for it. Passing the live subscription's Stripe ID
            // keeps the period pointed at whatever still pays for it.
            $this->openUsagePeriod->execute(
                $workspace,
                $workspace->currentPlan(),
                $workspace->activeSubscription()?->stripe_id,
            );
        });
    }
}
