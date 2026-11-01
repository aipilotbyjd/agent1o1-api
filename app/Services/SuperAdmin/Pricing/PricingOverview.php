<?php

namespace App\Services\SuperAdmin\Pricing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanPriceHistory;
use App\Models\Billing\PricingCoupon;
use App\Models\Billing\Subscription;
use App\Services\Billing\BillingSettings;

/**
 * The plans side by side, with who is on each and what they are worth. The
 * monthly-equivalent figure is derived from the Stripe price each live
 * subscription actually pays (retired prices included), so grandfathered
 * subscribers are valued at their own price, not the current one.
 */
class PricingOverview
{
    public function __construct(private readonly BillingSettings $settings, private readonly SuperAdminPackService $packs) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $plans = Plan::query()->whereNull('archived_at')->orderBy('sort_order')->get();

        $perPlan = $this->subscribersByPlan();

        return [
            'plans' => $plans->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'badge' => $plan->badge,
                'is_active' => $plan->is_active,
                'is_public' => $plan->is_public,
                'is_default' => $plan->slug === config('billing.default_plan'),
                'prices' => array_combine(
                    array_map(fn (BillingInterval $interval): string => $interval->value, BillingInterval::cases()),
                    array_map(fn (BillingInterval $interval): int => $plan->priceFor($interval), BillingInterval::cases()),
                ),
                'available_intervals' => array_map(fn (BillingInterval $interval): string => $interval->value, $plan->availableIntervals()),
                'credits_monthly' => $plan->credits_monthly,
                'limits' => $plan->limits,
                'features' => array_keys(array_filter((array) $plan->features)),
                'trial_days' => $plan->trial_days,
                'subscribers' => $perPlan[$plan->id]['subscribers'] ?? 0,
                'monthly_recurring_cents' => $perPlan[$plan->id]['mrr_cents'] ?? 0,
            ])->all(),
            'intervals' => $this->settings->intervals(),
            'packs' => $this->packs->all()->all(),
            'coupons' => [
                'active' => PricingCoupon::query()->where('is_active', true)->count(),
                'total' => PricingCoupon::query()->count(),
            ],
        ];
    }

    /**
     * @return array<string, array{subscribers: int, mrr_cents: int}>
     */
    private function subscribersByPlan(): array
    {
        $rows = Subscription::query()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->whereNotNull('plan_id')
            ->selectRaw('plan_id, stripe_price, count(*) as aggregate')
            ->groupBy('plan_id', 'stripe_price')
            ->get();

        $monthly = $this->monthlyEquivalentByPriceId();

        $report = [];

        foreach ($rows as $row) {
            $report[$row->plan_id] ??= ['subscribers' => 0, 'mrr_cents' => 0];
            $report[$row->plan_id]['subscribers'] += (int) $row->aggregate;
            $report[$row->plan_id]['mrr_cents'] += (int) round(($monthly[$row->stripe_price] ?? 0) * (int) $row->aggregate);
        }

        return $report;
    }

    /**
     * Every known Stripe price id (current and retired) as cents per month.
     * Lifetime is a one-off purchase, not recurring revenue, so it is left out.
     *
     * @return array<string, float>
     */
    private function monthlyEquivalentByPriceId(): array
    {
        $monthly = [];

        foreach (Plan::query()->get() as $plan) {
            foreach (BillingInterval::cases() as $interval) {
                $priceId = $plan->stripePriceId($interval);

                if ($priceId !== null && $interval->isRecurring()) {
                    $monthly[$priceId] = $this->perMonth($interval, $plan->priceFor($interval));
                }
            }
        }

        foreach (PlanPriceHistory::query()->get() as $row) {
            if ($row->interval->isRecurring()) {
                $monthly[$row->stripe_price_id] = $this->perMonth($row->interval, $row->amount_cents);
            }
        }

        return $monthly;
    }

    private function perMonth(BillingInterval $interval, int $cents): float
    {
        return match ($interval) {
            BillingInterval::Monthly => $cents,
            BillingInterval::Quarterly => $cents / 3,
            BillingInterval::Yearly => $cents / 12,
            BillingInterval::Lifetime => 0,
        };
    }
}
