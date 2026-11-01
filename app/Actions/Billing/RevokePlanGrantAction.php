<?php

namespace App\Actions\Billing;

use App\Enums\Billing\PlanGrantStatus;
use App\Models\Billing\PlanGrant;
use Illuminate\Support\Facades\DB;

/**
 * Withdraws a grant after a refund or a won dispute. Without this a refunded
 * lifetime purchase would entitle the workspace forever: the grant has no
 * expiry and no subscription status to lapse.
 *
 * Re-sizes the usage period to whatever the workspace is still entitled to,
 * which may push `credits_used` above the new `credits_limit`. That is the
 * intended reading: the credits were spent, the balance is simply zero (see
 * `UsagePeriod::remainingPlanCredits()`).
 */
class RevokePlanGrantAction
{
    public function __construct(private readonly OpenUsagePeriodForSubscriptionAction $openUsagePeriod) {}

    public function execute(PlanGrant $grant): void
    {
        if ($grant->status === PlanGrantStatus::Revoked) {
            return;
        }

        DB::transaction(function () use ($grant): void {
            $grant->update(['status' => PlanGrantStatus::Revoked]);

            $workspace = $grant->workspace;

            $this->openUsagePeriod->execute(
                $workspace,
                $workspace->currentPlan(),
                $workspace->activeSubscription()?->stripe_id,
            );
        });
    }
}
