<?php

namespace App\Services\Billing\Policies;

use App\Enums\Billing\CreditGrantSource;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Workspaces\Workspace;

/**
 * Decides what a usage period's allowance is: which kind of grant and how
 * many credits.
 *
 * - No plan resolved (a half-seeded install): unlimited, as before.
 * - A trialing subscription: the plan's `trial_credits`.
 * - A paid subscription or lifetime plan: the plan's monthly credits.
 * - Otherwise the free plan, through `FreeCreditPolicy`: the monthly credits
 *   for the owner's free workspace, 0 for any other.
 *
 * A subscription that is no longer valid (past due, canceled, incomplete)
 * entitles nothing: the workspace falls back to whatever `currentPlan()`
 * resolves without it.
 */
class CreditAllowancePolicy
{
    public function __construct(private readonly FreeCreditPolicy $free) {}

    /**
     * @return array{source: CreditGrantSource, credits: int|null, plan: Plan|null, subscription: Subscription|null}
     */
    public function decide(Workspace $workspace, ?Plan $plan = null, ?Subscription $subscription = null): array
    {
        if ($subscription !== null && ! $subscription->valid()) {
            $subscription = null;
            $plan = null;
        }

        $subscription ??= $workspace->activeSubscription();
        $plan ??= $workspace->currentPlan();

        if ($plan === null) {
            return ['source' => CreditGrantSource::Plan, 'credits' => null, 'plan' => null, 'subscription' => $subscription];
        }

        if ($subscription !== null && $subscription->onTrial()) {
            return ['source' => CreditGrantSource::Trial, 'credits' => $plan->trialCredits(), 'plan' => $plan, 'subscription' => $subscription];
        }

        if ($subscription === null && $workspace->activePlanGrant() === null) {
            return ['source' => CreditGrantSource::Free, 'credits' => $this->free->allowanceFor($workspace, $plan), 'plan' => $plan, 'subscription' => null];
        }

        return ['source' => CreditGrantSource::Plan, 'credits' => $plan->creditsMonthly(), 'plan' => $plan, 'subscription' => $subscription];
    }
}
