<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Enums\Billing\PlanLimit;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Billing\CreditGrantResource;
use App\Http\Resources\Api\Internal\V1\Billing\PlanGrantResource;
use App\Http\Resources\Api\Internal\V1\Billing\PlanResource;
use App\Http\Resources\Api\Internal\V1\Billing\SubscriptionResource;
use App\Http\Resources\Api\Internal\V1\Billing\UsagePeriodResource;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\Plan;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditOverage;
use App\Services\Billing\CreditWallet;
use App\Services\Billing\PlanLimitGate;
use App\Services\Billing\Policies\FreeCreditPolicy;

class BillingController extends Controller
{
    /**
     * A single aggregate read for the billing settings screen: the active
     * subscription (with its plan), any non-subscription entitlement (a
     * lifetime purchase or comp), and the current usage period's credit
     * balance. Transaction history and credit-pack purchases live on their
     * own endpoints since they paginate independently.
     *
     * `current_plan` is the one that actually applies, with both a
     * subscription and a grant in play, neither alone answers "what plan am
     * I on", so the screen should render this rather than `subscription.plan`.
     *
     * `credits_available` is the number that actually gates a run: the
     * period's remaining plan allowance plus the non-expiring `topup_credits`
     * bought via credit packs. `null` means unlimited.
     *
     * `credit_grants` breaks `credits_available` down by where the credits
     * came from (plan, free, trial, top-up, promo, referral, admin) and when
     * each expires, in the order they will be spent. `credit_debt` is usage
     * nothing could pay for yet; the next credits the workspace receives pay
     * it first.
     *
     * `free_credits` is non-null only for a workspace on the free plan: it
     * says whether this workspace receives the free monthly credits and, if
     * not, why (only the owner's first workspace does, the owner's email is
     * unverified, or the allowance was already used by another account).
     *
     * `limits` reports what `PlanLimitGate` will actually enforce on the next
     * create: render the "2 of 3 workflows" counter from this rather than
     * from `current_plan.limits`, which carries the raw cap with no usage
     * beside it. A `max` of `null` means unlimited.
     *
     * `overage` says whether the workspace keeps running once
     * `credits_available` hits zero, and what that is allowed to cost:
     * `credits_remaining` there is the second balance a run is gated on, so
     * a workspace showing `credits_available: 0` is only actually blocked
     * when this is `0` too. `PUT /billing/overage` changes it.
     *
     * `dunning` is non-null only while Stripe is failing to collect. Because
     * this app grants no grace period, `subscription` will already be null at
     * that point (the workspace has been dropped to the free plan) so this
     * is the only thing on the response that can explain why. Render it as a
     * banner pointing at `POST /billing/portal`.
     */
    public function overview(Workspace $workspace, PlanLimitGate $limits, CreditOverage $overage, CreditWallet $wallet, FreeCreditPolicy $freeCredits)
    {
        $this->requirePermission(Permission::BillingView);

        $subscription = $workspace->activeSubscription()?->load('plan');
        $planGrant = $workspace->activePlanGrant();
        $currentPlan = $workspace->currentPlan();

        return ApiResponse::success([
            'subscription' => $subscription ? SubscriptionResource::make($subscription) : null,
            'plan_grant' => $planGrant ? PlanGrantResource::make($planGrant->load('plan')) : null,
            'current_plan' => $currentPlan ? PlanResource::make($currentPlan) : null,
            'usage_period' => UsagePeriodResource::make($workspace->currentUsagePeriod()),
            'topup_credits' => $workspace->topup_credits,
            'credits_available' => $workspace->availableCredits(),
            'credit_grants' => CreditGrantResource::collection($wallet->spendableGrants($workspace)),
            'credit_debt' => (int) $workspace->fresh()?->credit_debt,
            'free_credits' => $this->freeCredits($workspace, $freeCredits),
            'limits' => $this->limitUsage($workspace, $currentPlan, $limits),
            'overage' => $this->overage($workspace, $overage),
            'dunning' => $this->dunning($workspace),
        ]);
    }

    /**
     * @return array{eligible: bool, reason: string|null}|null
     */
    private function freeCredits(Workspace $workspace, FreeCreditPolicy $policy): ?array
    {
        if ($workspace->activeSubscription() !== null || $workspace->activePlanGrant() !== null) {
            return null;
        }

        $reason = $policy->reasonDenied($workspace);

        return ['eligible' => $reason === null, 'reason' => $reason];
    }

    /**
     * The same shape `GET /billing/overage` returns, minus the settings-only
     * fields the overview has no use for: one fewer request for a screen
     * that has to render the balance and the overage allowance together.
     *
     * @return array{available: bool, enabled: bool, effective_limit: int|null, credits_used: int, credits_remaining: int|null}
     */
    private function overage(Workspace $workspace, CreditOverage $overage): array
    {
        $period = $workspace->currentUsagePeriod();

        return [
            'available' => $overage->isAvailableTo($workspace),
            'enabled' => $workspace->credit_overage_enabled,
            'effective_limit' => $overage->effectiveLimitFor($workspace),
            'credits_used' => $period->overage_credits_used,
            'credits_remaining' => $overage->remainingFor($workspace, $period),
        ];
    }

    /**
     * Read off the raw subscription row rather than `activeSubscription()`,
     * which is null precisely when this matters.
     *
     * @return array{started_at: string, attempts: int, invoice_id: string|null}|null
     */
    private function dunning(Workspace $workspace): ?array
    {
        $subscription = $workspace->subscription('default');

        if (! $subscription?->inDunning()) {
            return null;
        }

        return [
            'started_at' => $subscription->dunning_started_at->toIso8601String(),
            'attempts' => $subscription->dunning_attempts,
            'invoice_id' => $subscription->dunning_invoice_id,
        ];
    }

    /**
     * @return array<string, array{used: int, max: int|null}>
     */
    private function limitUsage(Workspace $workspace, ?Plan $plan, PlanLimitGate $limits): array
    {
        $usage = [];

        foreach (PlanLimit::cases() as $limit) {
            $usage[$limit->value] = [
                'used' => $limits->usage($workspace, $limit),
                'max' => $plan?->limit($limit),
            ];
        }

        return $usage;
    }
}
