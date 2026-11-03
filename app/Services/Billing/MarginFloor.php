<?php

namespace App\Services\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;

/**
 * Whether every way of buying credits earns more per credit than a credit can
 * cost. Model calls are billed at cost (`billing.credit_value_usd` per
 * credit), so a plan, pack or overage rate that earns less than that per
 * credit loses money on every credit a customer spends on tokens, however
 * accurate the model prices are.
 *
 * Each offer is checked twice:
 *
 * - its gross earnings per credit against the floor for its kind
 *   (`billing.margin_floor.monthly_usd_per_credit` for monthly plans and
 *   overage, `prepaid_usd_per_credit` for quarterly, yearly, lifetime and
 *   packs), and
 * - its earnings after the card fee (`payment_fee_rate`) against the cost of
 *   a credit, so no offer can ever be sold below cost.
 *
 * Plans are judged on the intervals they would be sold on: the plan's own
 * `enabled_intervals` and the estate-wide interval switches, but not whether
 * a Stripe price is configured yet, so the catalog can be checked before it
 * is wired to Stripe.
 */
class MarginFloor
{
    public function __construct(private readonly CreditOverage $overage) {}

    /**
     * Every offer currently for sale, checked.
     *
     * @return list<array{kind: string, subject: string, interval: string|null, price_usd: float, credits: int, usd_per_credit: float, net_usd_per_credit: float, floor_usd_per_credit: float, cost_usd_per_credit: float, margin_percent: float, passes: bool}>
     */
    public function report(): array
    {
        $rows = [];

        foreach (Plan::query()->where('is_active', true)->orderBy('sort_order')->get() as $plan) {
            array_push($rows, ...$this->forPlan($plan->only([
                'slug', 'credits_monthly', 'enabled_intervals',
                'price_monthly', 'price_quarterly', 'price_yearly', 'price_lifetime',
            ])));
        }

        return [...$rows, ...$this->forPacks(), $this->forOverage()];
    }

    /**
     * The offers that earn less than they should.
     *
     * @return list<array{kind: string, subject: string, interval: string|null, price_usd: float, credits: int, usd_per_credit: float, net_usd_per_credit: float, floor_usd_per_credit: float, cost_usd_per_credit: float, margin_percent: float, passes: bool}>
     */
    public function violations(): array
    {
        return array_values(array_filter($this->report(), fn (array $row): bool => ! $row['passes']));
    }

    /**
     * One plan's offers, from a `Plan` row's attributes or a `database/setup/plans.php`
     * entry. Free plans (no price on any interval) are skipped: their cost is
     * bounded by the plan's dollar caps instead.
     *
     * @param  array<string, mixed>  $plan
     * @return list<array{kind: string, subject: string, interval: string|null, price_usd: float, credits: int, usd_per_credit: float, net_usd_per_credit: float, floor_usd_per_credit: float, cost_usd_per_credit: float, margin_percent: float, passes: bool}>
     */
    public function forPlan(array $plan): array
    {
        $creditsMonthly = (int) ($plan['credits_monthly'] ?? 0);

        if ($creditsMonthly <= 0) {
            return [];
        }

        $rows = [];

        foreach (BillingInterval::cases() as $interval) {
            $cents = (int) ($plan[$this->priceColumn($interval)] ?? 0);

            if ($cents <= 0 || ! $this->wouldSell($plan, $interval)) {
                continue;
            }

            $rows[] = $this->row(
                kind: 'plan',
                subject: (string) $plan['slug'],
                interval: $interval->value,
                priceUsd: $cents / 100,
                credits: $creditsMonthly * $this->months($interval),
                floor: $interval === BillingInterval::Monthly ? $this->monthlyFloor() : $this->prepaidFloor(),
            );
        }

        return $rows;
    }

    /**
     * @return list<array{kind: string, subject: string, interval: string|null, price_usd: float, credits: int, usd_per_credit: float, net_usd_per_credit: float, floor_usd_per_credit: float, cost_usd_per_credit: float, margin_percent: float, passes: bool}>
     */
    public function forPacks(): array
    {
        $rows = [];

        foreach ((array) config('billing.packs', []) as $key => $pack) {
            $credits = (int) ($pack['credits'] ?? 0);

            if ($credits <= 0) {
                continue;
            }

            $rows[] = $this->row('pack', (string) $key, null, ((int) ($pack['price_cents'] ?? 0)) / 100, $credits, $this->prepaidFloor());
        }

        return $rows;
    }

    /**
     * Overage is priced per credit, so one credit at the overage rate stands
     * for all of it.
     *
     * @return array{kind: string, subject: string, interval: string|null, price_usd: float, credits: int, usd_per_credit: float, net_usd_per_credit: float, floor_usd_per_credit: float, cost_usd_per_credit: float, margin_percent: float, passes: bool}
     */
    public function forOverage(): array
    {
        return $this->row('overage', 'overage', null, $this->overage->creditValueUsd(), 1, $this->monthlyFloor());
    }

    /**
     * How many months of credits one payment on `$interval` buys.
     */
    public function months(BillingInterval $interval): int
    {
        return match ($interval) {
            BillingInterval::Monthly => 1,
            BillingInterval::Quarterly => 3,
            BillingInterval::Yearly => 12,
            BillingInterval::Lifetime => max(1, (int) config('billing.margin_floor.lifetime_months', 36)),
        };
    }

    /**
     * @return array{kind: string, subject: string, interval: string|null, price_usd: float, credits: int, usd_per_credit: float, net_usd_per_credit: float, floor_usd_per_credit: float, cost_usd_per_credit: float, margin_percent: float, passes: bool}
     */
    private function row(string $kind, string $subject, ?string $interval, float $priceUsd, int $credits, float $floor): array
    {
        $cost = $this->costPerCredit();
        $perCredit = $priceUsd / max(1, $credits);
        $net = $perCredit * (1 - $this->paymentFeeRate());

        return [
            'kind' => $kind,
            'subject' => $subject,
            'interval' => $interval,
            'price_usd' => round($priceUsd, 2),
            'credits' => $credits,
            'usd_per_credit' => round($perCredit, 6),
            'net_usd_per_credit' => round($net, 6),
            'floor_usd_per_credit' => $floor,
            'cost_usd_per_credit' => $cost,
            'margin_percent' => $net > 0 ? round(($net - $cost) / $net * 100, 1) : -100.0,
            'passes' => $perCredit + 1e-9 >= $floor && $net > $cost,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function wouldSell(array $plan, BillingInterval $interval): bool
    {
        if (! $interval->isEnabledGlobally()) {
            return false;
        }

        $enabled = $plan['enabled_intervals'] ?? null;

        return $enabled === null || in_array($interval->value, (array) $enabled, strict: true);
    }

    private function priceColumn(BillingInterval $interval): string
    {
        return 'price_'.$interval->value;
    }

    private function costPerCredit(): float
    {
        return (float) config('billing.credit_value_usd');
    }

    private function monthlyFloor(): float
    {
        return max($this->costPerCredit(), (float) config('billing.margin_floor.monthly_usd_per_credit'));
    }

    private function prepaidFloor(): float
    {
        return max($this->costPerCredit(), (float) config('billing.margin_floor.prepaid_usd_per_credit'));
    }

    private function paymentFeeRate(): float
    {
        return (float) config('billing.margin_floor.payment_fee_rate', 0);
    }
}
