<?php

namespace App\Services\SuperAdmin\Revenue;

use App\Enums\Billing\BillingInterval;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Every subscription as one flat row with its monthly recurring revenue
 * worked out, which is all the revenue and growth numbers are built from.
 *
 * Money is integer minor units (cents) in `config('cashier.currency')`: the
 * only currency this app sells in, so nothing is converted or summed across
 * currencies. A plan's price is read as it is today, so a past month is
 * valued at current prices; the nightly `revenue:snapshot` is what keeps a
 * true history across price changes.
 *
 * A subscription pays from the end of its trial (or its start, without one)
 * until `ends_at`. Statuses that never collected money (`incomplete`,
 * `incomplete_expired`, `unpaid`, `paused`) are left out.
 */
class SubscriptionLedger
{
    /** Statuses whose subscriptions have paid, or are paying, for something. */
    private const COUNTED = ['active', 'past_due', 'trialing', 'canceled'];

    /**
     * @var list<array{id: string, workspace_id: string, workspace: ?string, plan_id: ?string, plan: string, plan_name: string, interval: string, price: int, mrr: int, status: string, created_at: CarbonImmutable, billable_from: CarbonImmutable, ended_at: ?CarbonImmutable, trial_ends_at: ?CarbonImmutable, dunning_started_at: ?CarbonImmutable, dunning_attempts: int, source: string}>|null
     */
    private ?array $rows = null;

    public function currency(): string
    {
        return strtoupper((string) config('cashier.currency', 'usd'));
    }

    /**
     * @return list<array{id: string, workspace_id: string, workspace: ?string, plan_id: ?string, plan: string, plan_name: string, interval: string, price: int, mrr: int, status: string, created_at: CarbonImmutable, billable_from: CarbonImmutable, ended_at: ?CarbonImmutable, trial_ends_at: ?CarbonImmutable, dunning_started_at: ?CarbonImmutable, dunning_attempts: int, source: string}>
     */
    public function rows(): array
    {
        return $this->rows ??= $this->load();
    }

    /**
     * Rows paying at `$at`: started billing, not yet ended, and worth something.
     *
     * @return list<array<string, mixed>>
     */
    public function payingAt(CarbonImmutable $at): array
    {
        return array_values(array_filter(
            $this->rows(),
            fn (array $row): bool => $row['mrr'] > 0
                && $row['billable_from']->lte($at)
                && ($row['ended_at'] === null || $row['ended_at']->gt($at)),
        ));
    }

    public function mrrAt(CarbonImmutable $at): int
    {
        return (int) array_sum(array_column($this->payingAt($at), 'mrr'));
    }

    /**
     * @return list<array{id: string, workspace_id: string, workspace: ?string, plan_id: ?string, plan: string, plan_name: string, interval: string, price: int, mrr: int, status: string, created_at: CarbonImmutable, billable_from: CarbonImmutable, ended_at: ?CarbonImmutable, trial_ends_at: ?CarbonImmutable, dunning_started_at: ?CarbonImmutable, dunning_attempts: int, source: string}>
     */
    private function load(): array
    {
        $rows = DB::table('subscriptions as s')
            ->leftJoin('plans as p', 'p.id', '=', 's.plan_id')
            ->leftJoin('workspaces as w', 'w.id', '=', 's.workspace_id')
            ->leftJoin('users as u', 'u.id', '=', 'w.owner_id')
            ->whereIn('s.stripe_status', self::COUNTED)
            ->select([
                's.id', 's.workspace_id', 's.stripe_status', 's.stripe_price', 's.trial_ends_at', 's.ends_at', 's.created_at',
                's.dunning_started_at', 's.dunning_attempts',
                'w.name as workspace_name', 'u.discovery_source',
                'p.id as plan_id', 'p.slug as plan_slug', 'p.name as plan_name',
                'p.price_monthly', 'p.price_quarterly', 'p.price_yearly',
                'p.stripe_price_id_monthly', 'p.stripe_price_id_quarterly', 'p.stripe_price_id_yearly', 'p.stripe_price_id_lifetime',
            ])
            ->orderBy('s.created_at')
            ->cursor();

        $ledger = [];

        foreach ($rows as $row) {
            $interval = $this->interval($row);
            $price = (int) match ($interval) {
                BillingInterval::Quarterly->value => $row->price_quarterly,
                BillingInterval::Yearly->value => $row->price_yearly,
                BillingInterval::Lifetime->value => 0,
                default => $row->price_monthly,
            };

            $createdAt = CarbonImmutable::parse($row->created_at);
            $trialEndsAt = $row->trial_ends_at === null ? null : CarbonImmutable::parse($row->trial_ends_at);

            $ledger[] = [
                'id' => $row->id,
                'workspace_id' => $row->workspace_id,
                'workspace' => $row->workspace_name,
                'plan_id' => $row->plan_id,
                'plan' => $row->plan_slug ?? 'unknown',
                'plan_name' => $row->plan_name ?? 'Unknown plan',
                'interval' => $interval,
                'price' => $price,
                'mrr' => $this->monthly($price, $interval),
                'status' => $row->stripe_status,
                'created_at' => $createdAt,
                'billable_from' => $trialEndsAt !== null && $trialEndsAt->gt($createdAt) ? $trialEndsAt : $createdAt,
                'ended_at' => $row->ends_at === null ? null : CarbonImmutable::parse($row->ends_at),
                'trial_ends_at' => $trialEndsAt,
                'dunning_started_at' => $row->dunning_started_at === null ? null : CarbonImmutable::parse($row->dunning_started_at),
                'dunning_attempts' => (int) $row->dunning_attempts,
                'source' => $row->discovery_source ?: 'unknown',
            ];
        }

        return $ledger;
    }

    private function interval(object $row): string
    {
        return match ($row->stripe_price) {
            $row->stripe_price_id_quarterly => BillingInterval::Quarterly->value,
            $row->stripe_price_id_yearly => BillingInterval::Yearly->value,
            $row->stripe_price_id_lifetime => BillingInterval::Lifetime->value,
            default => BillingInterval::Monthly->value,
        };
    }

    private function monthly(int $price, string $interval): int
    {
        return match ($interval) {
            BillingInterval::Quarterly->value => (int) round($price / 3),
            BillingInterval::Yearly->value => (int) round($price / 12),
            BillingInterval::Lifetime->value => 0,
            default => $price,
        };
    }
}
