<?php

namespace App\Services\Billing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\CreditGrantSource;
use App\Enums\Billing\PlanGrantSource;
use App\Models\Billing\CreditGrant;
use App\Models\Billing\CreditPack;
use App\Models\Billing\Plan;
use App\Models\Workspaces\Workspace;

/**
 * What a charge earned, in US dollars: the revenue side of the books, set
 * against the provider cost in `ai_call_events`.
 *
 * Each credit is worth what the customer paid for it:
 *
 * - plan allowance and roll-over credits: the plan price on the workspace's
 *   billing interval divided by the credits that price buys (a lifetime
 *   purchase spread over `billing.margin_floor.lifetime_months`);
 * - top-up credits: what that credit pack cost per credit;
 * - overage credits: the overage rate (`CreditOverage::creditValueUsd()`);
 * - free, trial, promo, referral, admin and migrated credits, and debt:
 *   nothing. They are the platform's cost of acquiring and keeping the
 *   customer, which is exactly what the reports need to show.
 */
class CreditRevenue
{
    public function __construct(
        private readonly CreditOverage $overage,
        private readonly MarginFloor $floor,
    ) {}

    /**
     * @param  list<array{grant: CreditGrant, credits: int}>  $allocations
     */
    public function forCharge(Workspace $workspace, array $allocations, int $overageCredits): float
    {
        $usd = 0.0;

        foreach ($allocations as $allocation) {
            $usd += $allocation['credits'] * $this->usdPerCredit($workspace, $allocation['grant']);
        }

        $usd += $overageCredits * $this->overage->creditValueUsd();

        return round($usd, 6);
    }

    /**
     * What one credit from `$grant` earned.
     */
    public function usdPerCredit(Workspace $workspace, CreditGrant $grant): float
    {
        return match ($grant->source) {
            CreditGrantSource::Plan, CreditGrantSource::Rollover => $this->planUsdPerCredit($workspace),
            CreditGrantSource::Topup => $this->packUsdPerCredit($grant),
            default => 0.0,
        };
    }

    /**
     * What one allowance credit earns on the workspace's current plan and
     * billing interval. Zero on a free plan, a comp or a referral grant.
     */
    public function planUsdPerCredit(Workspace $workspace): float
    {
        $subscription = $workspace->activeSubscription();
        $plan = $subscription?->plan;

        if ($plan instanceof Plan && $plan->creditsMonthly() > 0) {
            $interval = $this->intervalFor($plan, (string) $subscription->stripe_price) ?? BillingInterval::Monthly;

            return $this->perCredit($plan, $interval);
        }

        $grant = $workspace->activePlanGrant();

        if ($grant?->plan !== null && $grant->source === PlanGrantSource::LifetimePurchase) {
            return $this->perCredit($grant->plan, BillingInterval::Lifetime);
        }

        return 0.0;
    }

    private function packUsdPerCredit(CreditGrant $grant): float
    {
        if ($grant->reference_type !== (new CreditPack)->getMorphClass() || $grant->reference_id === null) {
            return 0.0;
        }

        $pack = CreditPack::query()->find($grant->reference_id, ['id', 'credits_amount', 'price_cents']);

        return $pack === null || $pack->credits_amount <= 0 ? 0.0 : $pack->price_cents / 100 / $pack->credits_amount;
    }

    private function perCredit(Plan $plan, BillingInterval $interval): float
    {
        $credits = $plan->creditsMonthly() * $this->floor->months($interval);

        return $credits > 0 ? $plan->priceFor($interval) / 100 / $credits : 0.0;
    }

    private function intervalFor(Plan $plan, string $stripePrice): ?BillingInterval
    {
        foreach (BillingInterval::cases() as $interval) {
            if ($stripePrice !== '' && $plan->stripePriceId($interval) === $stripePrice) {
                return $interval;
            }
        }

        return null;
    }
}
