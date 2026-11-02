<?php

namespace App\Services\Intelligence;

use App\Models\Billing\Plan;
use App\Services\Billing\CreditOverage;
use App\Services\Billing\MarginFloor;
use Illuminate\Support\Facades\DB;

/**
 * "What if Pro cost $79 for 12,000 credits?" Replays each customer's real
 * usage over the last `$days` days, as a monthly average, against the plans
 * as they are and as proposed, and compares revenue, provider cost and
 * margin, plus how many customers would pay more or run past their
 * allowance. Nothing is changed.
 *
 * Both sides are worked out the same way (plan price plus overage beyond the
 * allowance), so the comparison is like for like. A customer whose allowance
 * shrinks below what they use is assumed to keep using it and pay overage;
 * on a free plan, where there is no overage, usage is cut to the allowance
 * and its cost with it.
 */
class PricingSimulator
{
    public function __construct(
        private readonly CreditOverage $overage,
        private readonly MarginFloor $floor,
    ) {}

    /**
     * @param  array{plans?: array<string, array{price_monthly?: int, credits_monthly?: int}>, overage_credit_value_usd?: float|null}  $proposal
     * @return array<string, mixed>
     */
    public function simulate(array $proposal, int $days = 90): array
    {
        $days = max(7, min($days, 365));
        $months = $days / 30;
        $plans = Plan::query()->get()->keyBy('slug');
        $proposedPlans = (array) ($proposal['plans'] ?? []);
        $currentOverage = $this->overage->creditValueUsd();
        $proposedOverage = isset($proposal['overage_credit_value_usd']) ? max((float) config('billing.credit_value_usd'), (float) $proposal['overage_credit_value_usd']) : $currentOverage;

        $customers = DB::table('fact_workspace_daily')
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('workspace_id, max(plan_slug) as plan, sum(credits_used) as credits, sum(provider_cost_usd) as cost')
            ->groupBy('workspace_id')
            ->get();

        $totals = ['current' => $this->emptyTotals(), 'proposed' => $this->emptyTotals()];
        $byPlan = [];
        $payMore = 0;
        $newlyOver = 0;

        foreach ($customers as $customer) {
            $plan = $plans[$customer->plan] ?? null;

            if ($plan === null) {
                continue;
            }

            $usage = (float) $customer->credits / $months;
            $cost = (float) $customer->cost / $months;
            $current = $this->monthly($plan->price_monthly, $plan->creditsMonthly(), $usage, $cost, $currentOverage);
            $proposedPlan = $proposedPlans[$plan->slug] ?? [];
            $proposed = $this->monthly(
                (int) ($proposedPlan['price_monthly'] ?? $plan->price_monthly),
                (int) ($proposedPlan['credits_monthly'] ?? $plan->creditsMonthly()),
                $usage,
                $cost,
                $proposedOverage,
            );

            $payMore += $proposed['revenue'] > $current['revenue'] + 0.01 ? 1 : 0;
            $newlyOver += $proposed['over_allowance'] && ! $current['over_allowance'] ? 1 : 0;

            foreach (['current' => $current, 'proposed' => $proposed] as $side => $values) {
                $this->add($totals[$side], $values);
                $byPlan[$plan->slug][$side] ??= $this->emptyTotals();
                $this->add($byPlan[$plan->slug][$side], $values);
            }

            $byPlan[$plan->slug]['customers'] = ($byPlan[$plan->slug]['customers'] ?? 0) + 1;
        }

        $floorChecks = [];

        foreach ($proposedPlans as $slug => $changes) {
            $plan = $plans[$slug] ?? null;

            if ($plan !== null) {
                array_push($floorChecks, ...$this->floor->forPlan([...$plan->only(['slug', 'credits_monthly', 'enabled_intervals', 'price_monthly', 'price_quarterly', 'price_yearly', 'price_lifetime']), ...$changes]));
            }
        }

        return [
            'days' => $days,
            'customers' => $customers->count(),
            'current' => $this->summarise($totals['current']),
            'proposed' => $this->summarise($totals['proposed']),
            'customers_paying_more' => $payMore,
            'customers_newly_over_allowance' => $newlyOver,
            'by_plan' => collect($byPlan)->map(fn (array $row, string $slug): array => [
                'plan' => $slug,
                'customers' => $row['customers'],
                'current' => $this->summarise($row['current']),
                'proposed' => $this->summarise($row['proposed']),
            ])->values()->all(),
            'margin_floor' => $floorChecks,
        ];
    }

    /**
     * @return array{revenue: float, cost: float, over_allowance: bool}
     */
    private function monthly(int $priceCents, int $allowance, float $usage, float $cost, float $overageRate): array
    {
        $price = $priceCents / 100;

        if ($price <= 0) {
            $share = $usage > 0 && $allowance > 0 ? min(1.0, $allowance / $usage) : 1.0;

            return ['revenue' => 0.0, 'cost' => $cost * $share, 'over_allowance' => $usage > $allowance];
        }

        $over = max(0.0, $usage - $allowance);

        return ['revenue' => $price + $over * $overageRate, 'cost' => $cost, 'over_allowance' => $over > 0];
    }

    /**
     * @return array{revenue: float, cost: float}
     */
    private function emptyTotals(): array
    {
        return ['revenue' => 0.0, 'cost' => 0.0];
    }

    /**
     * @param  array{revenue: float, cost: float}  $totals
     * @param  array{revenue: float, cost: float, over_allowance: bool}  $values
     */
    private function add(array &$totals, array $values): void
    {
        $totals['revenue'] += $values['revenue'];
        $totals['cost'] += $values['cost'];
    }

    /**
     * @param  array{revenue: float, cost: float}  $totals
     * @return array{monthly_revenue_usd: float, monthly_provider_cost_usd: float, monthly_gross_margin_usd: float, gross_margin_percent: float|null}
     */
    private function summarise(array $totals): array
    {
        $net = $totals['revenue'] * (1 - (float) config('billing.margin_floor.payment_fee_rate', 0));
        $margin = $net - $totals['cost'];

        return [
            'monthly_revenue_usd' => round($totals['revenue'], 2),
            'monthly_provider_cost_usd' => round($totals['cost'], 2),
            'monthly_gross_margin_usd' => round($margin, 2),
            'gross_margin_percent' => $totals['revenue'] > 0 ? round($margin / $totals['revenue'] * 100, 1) : null,
        ];
    }
}
