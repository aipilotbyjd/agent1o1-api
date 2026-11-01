<?php

namespace App\Services\SuperAdmin\Revenue;

use App\Enums\Billing\CreditPackStatus;
use App\Enums\Billing\OverageInvoiceAttemptStatus;
use App\Models\Billing\CreditPack;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\OverageInvoiceAttempt;
use App\Models\Billing\UsagePeriod;
use App\Models\SuperAdmin\RevenueDailySnapshot;
use Carbon\CarbonImmutable;

/**
 * Revenue figures for the Super Admin dashboard, all derived from the
 * subscription ledger. Every method takes explicit dates or day counts and
 * works in UTC.
 */
class RevenueMetrics
{
    public function __construct(private readonly SubscriptionLedger $ledger) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(int $days): array
    {
        $now = CarbonImmutable::now('UTC');
        $before = $now->subDays($days);

        $paying = $this->ledger->payingAt($now);
        $mrr = (int) array_sum(array_column($paying, 'mrr'));
        $previousMrr = $this->ledger->mrrAt($before);
        $previousSubscribers = count($this->ledger->payingAt($before));

        $rows = $this->ledger->rows();

        return [
            'currency' => $this->ledger->currency(),
            'window_days' => $days,
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'arpu' => $paying === [] ? 0 : (int) round($mrr / count($paying)),
            'paying_subscribers' => count($paying),
            'trialing' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'trialing' && $row['trial_ends_at']?->gt($now))),
            'past_due' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'past_due')),
            'net_new_mrr' => $mrr - $previousMrr,
            'previous' => [
                'mrr' => $previousMrr,
                'paying_subscribers' => $previousSubscribers,
                'mrr_change_percent' => $previousMrr === 0 ? null : round(($mrr - $previousMrr) / $previousMrr * 100, 1),
            ],
        ];
    }

    /**
     * The MRR waterfall per month: where the month started, what was added by
     * new customers and returning ones, what was lost to cancellations, and
     * `other_change` for everything else (upgrades, downgrades and price
     * changes, which cannot be told apart without a plan-change history).
     *
     * @return array{currency: string, months: list<array<string, mixed>>}
     */
    public function mrrMovement(int $months): array
    {
        $now = CarbonImmutable::now('UTC');
        $result = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $start = $now->startOfMonth()->subMonths($offset);
            $end = min($start->endOfMonth(), $now);

            $startingMrr = $this->ledger->mrrAt($start->subSecond());
            $endingMrr = $this->ledger->mrrAt($end);

            $new = 0;
            $reactivation = 0;
            $churned = 0;
            $newCount = 0;
            $churnedCount = 0;

            foreach ($this->ledger->rows() as $row) {
                if ($row['mrr'] <= 0) {
                    continue;
                }

                if ($row['billable_from']->between($start, $end)) {
                    if ($this->hadEarlierSubscription($row)) {
                        $reactivation += $row['mrr'];
                    } else {
                        $new += $row['mrr'];
                        $newCount++;
                    }
                }

                if ($row['ended_at'] !== null && $row['ended_at']->between($start, $end) && $row['billable_from']->lte($row['ended_at'])) {
                    $churned += $row['mrr'];
                    $churnedCount++;
                }
            }

            $result[] = [
                'month' => $start->format('Y-m'),
                'starting_mrr' => $startingMrr,
                'new' => $new,
                'reactivation' => $reactivation,
                'churned' => -$churned,
                'other_change' => $endingMrr - $startingMrr - $new - $reactivation + $churned,
                'ending_mrr' => $endingMrr,
                'new_customers' => $newCount,
                'churned_customers' => $churnedCount,
            ];
        }

        return ['currency' => $this->ledger->currency(), 'months' => $result];
    }

    /**
     * MRR and subscribers per day, week or month. Days that have a nightly
     * snapshot use it (so a later price change does not rewrite them); the
     * rest are worked out from the ledger.
     *
     * @return array{currency: string, granularity: string, points: list<array{date: string, mrr: int, subscribers: int, source: string}>}
     */
    public function timeseries(CarbonImmutable $from, CarbonImmutable $to, string $granularity): array
    {
        $snapshots = RevenueDailySnapshot::query()
            ->where('currency', $this->ledger->currency())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('date, sum(mrr) as mrr, sum(subscribers) as subscribers')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row): string => substr((string) $row->date, 0, 10));

        $points = [];
        $step = match ($granularity) {
            'week' => '1 week',
            'month' => '1 month',
            default => '1 day',
        };

        for ($day = $from->startOfDay(); $day->lte($to); $day = $day->modify("+{$step}")) {
            $at = min($day->endOfDay(), CarbonImmutable::now('UTC'));
            $snapshot = $snapshots->get($day->toDateString());

            $points[] = $snapshot !== null
                ? ['date' => $day->toDateString(), 'mrr' => (int) $snapshot->mrr, 'subscribers' => (int) $snapshot->subscribers, 'source' => 'snapshot']
                : ['date' => $day->toDateString(), 'mrr' => $this->ledger->mrrAt($at), 'subscribers' => count($this->ledger->payingAt($at)), 'source' => 'ledger'];
        }

        return ['currency' => $this->ledger->currency(), 'granularity' => $granularity, 'points' => $points];
    }

    /**
     * Subscribers, MRR and share per plan, with the plan's MRR a month ago.
     *
     * @return array{currency: string, total_mrr: int, plans: list<array<string, mixed>>}
     */
    public function plans(): array
    {
        $now = CarbonImmutable::now('UTC');
        $current = $this->groupByPlan($this->ledger->payingAt($now));
        $previous = $this->groupByPlan($this->ledger->payingAt($now->subDays(30)));
        $total = (int) array_sum(array_column($current, 'mrr'));

        $plans = [];

        foreach ($current as $slug => $plan) {
            $plans[] = [
                'plan' => $slug,
                'name' => $plan['name'],
                'subscribers' => $plan['subscribers'],
                'mrr' => $plan['mrr'],
                'share_percent' => $total === 0 ? 0.0 : round($plan['mrr'] / $total * 100, 1),
                'mrr_30_days_ago' => $previous[$slug]['mrr'] ?? 0,
                'subscribers_30_days_ago' => $previous[$slug]['subscribers'] ?? 0,
            ];
        }

        usort($plans, fn (array $a, array $b): int => $b['mrr'] <=> $a['mrr']);

        return ['currency' => $this->ledger->currency(), 'total_mrr' => $total, 'plans' => $plans];
    }

    /**
     * Logo churn (customers lost) and revenue churn (MRR lost) over the last `$days`.
     *
     * @return array<string, mixed>
     */
    public function churn(int $days): array
    {
        $now = CarbonImmutable::now('UTC');
        $since = $now->subDays($days);

        $startingRows = $this->ledger->payingAt($since);
        $startingMrr = (int) array_sum(array_column($startingRows, 'mrr'));

        $lost = array_values(array_filter(
            $this->ledger->rows(),
            fn (array $row): bool => $row['mrr'] > 0 && $row['ended_at'] !== null
                && $row['ended_at']->between($since, $now) && $row['billable_from']->lte($row['ended_at']),
        ));
        $lostMrr = (int) array_sum(array_column($lost, 'mrr'));

        return [
            'currency' => $this->ledger->currency(),
            'window_days' => $days,
            'starting_subscribers' => count($startingRows),
            'churned_subscribers' => count($lost),
            'logo_churn_percent' => $startingRows === [] ? null : round(count($lost) / count($startingRows) * 100, 2),
            'starting_mrr' => $startingMrr,
            'churned_mrr' => $lostMrr,
            'revenue_churn_percent' => $startingMrr === 0 ? null : round($lostMrr / $startingMrr * 100, 2),
            'by_plan' => $this->groupLost($lost),
            // Stripe's cancellation reason is not stored locally.
            'cancel_reasons' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trials(int $days): array
    {
        $now = CarbonImmutable::now('UTC');
        $since = $now->subDays($days);

        $trials = array_values(array_filter(
            $this->ledger->rows(),
            fn (array $row): bool => $row['trial_ends_at'] !== null && $row['trial_ends_at']->gt($row['created_at']) && $row['created_at']->gte($since),
        ));

        $finished = array_values(array_filter($trials, fn (array $row): bool => $row['trial_ends_at']->lte($now)));
        $converted = array_values(array_filter($finished, fn (array $row): bool => $row['mrr'] > 0 && ($row['ended_at'] === null || $row['ended_at']->gt($row['trial_ends_at']))));

        $lengths = array_map(fn (array $row): int => (int) $row['created_at']->diffInDays($row['trial_ends_at'], true), $converted);

        return [
            'window_days' => $days,
            'trials_started' => count($trials),
            'trials_finished' => count($finished),
            'still_trialing' => count($trials) - count($finished),
            'converted' => count($converted),
            'conversion_rate_percent' => $finished === [] ? null : round(count($converted) / count($finished) * 100, 1),
            'average_days_to_convert' => $lengths === [] ? null : round(array_sum($lengths) / count($lengths), 1),
        ];
    }

    /**
     * Subscriptions Stripe is failing to collect on, with the amount at risk.
     *
     * @return array{currency: string, total_at_risk: int, subscriptions: list<array<string, mixed>>}
     */
    public function failedPayments(): array
    {
        $now = CarbonImmutable::now('UTC');

        $rows = array_values(array_filter(
            $this->ledger->rows(),
            fn (array $row): bool => $row['dunning_started_at'] !== null && $row['status'] !== 'canceled',
        ));

        $subscriptions = array_map(fn (array $row): array => [
            'subscription_id' => $row['id'],
            'workspace_id' => $row['workspace_id'],
            'workspace' => $row['workspace'],
            'plan' => $row['plan'],
            'interval' => $row['interval'],
            'amount_at_risk' => $row['price'],
            'days_overdue' => (int) $row['dunning_started_at']->diffInDays($now, true),
            'attempts' => $row['dunning_attempts'],
            'failing_since' => $row['dunning_started_at']->toIso8601String(),
        ], $rows);

        usort($subscriptions, fn (array $a, array $b): int => $b['days_overdue'] <=> $a['days_overdue']);

        return [
            'currency' => $this->ledger->currency(),
            'total_at_risk' => (int) array_sum(array_column($subscriptions, 'amount_at_risk')),
            'subscriptions' => $subscriptions,
        ];
    }

    /**
     * Paid retention by the month a workspace first started paying: of those
     * who started in a month, the share still paying k months later.
     *
     * @return array{cohorts: list<array{cohort: string, workspaces: int, retention_percent: list<?float>}>}
     */
    public function cohorts(int $months): array
    {
        $now = CarbonImmutable::now('UTC');

        $firstPaid = [];

        foreach ($this->ledger->rows() as $row) {
            if ($row['mrr'] <= 0 || $row['billable_from']->gt($now)) {
                continue;
            }

            $current = $firstPaid[$row['workspace_id']] ?? null;
            $firstPaid[$row['workspace_id']] = $current === null || $row['billable_from']->lt($current) ? $row['billable_from'] : $current;
        }

        $byWorkspace = [];

        foreach ($this->ledger->rows() as $row) {
            if ($row['mrr'] > 0) {
                $byWorkspace[$row['workspace_id']][] = $row;
            }
        }

        $cohorts = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $start = $now->startOfMonth()->subMonths($offset);
            $members = array_keys(array_filter($firstPaid, fn (CarbonImmutable $paidAt): bool => $paidAt->between($start, $start->endOfMonth())));

            $retention = [];

            for ($k = 0; $k <= $offset; $k++) {
                $checkAt = min($start->addMonths($k)->endOfMonth(), $now);
                $still = count(array_filter($members, fn (string $workspace): bool => $this->payingWorkspaceAt($byWorkspace[$workspace] ?? [], $checkAt)));
                $retention[] = $members === [] ? null : round($still / count($members) * 100, 1);
            }

            $cohorts[] = ['cohort' => $start->format('Y-m'), 'workspaces' => count($members), 'retention_percent' => $retention];
        }

        return ['cohorts' => $cohorts];
    }

    /**
     * Estimated lifetime value: ARPU divided by the monthly logo churn of the
     * last 90 days. `null` where nothing has churned, since the estimate would
     * be infinite.
     *
     * @return array{currency: string, by_plan: list<array<string, mixed>>, by_source: list<array<string, mixed>>}
     */
    public function ltv(): array
    {
        return [
            'currency' => $this->ledger->currency(),
            'by_plan' => $this->ltvBy('plan', 'plan_name'),
            'by_source' => $this->ltvBy('source', 'source'),
        ];
    }

    /**
     * Credits sold, granted and used, and what overage billed.
     *
     * @return array<string, mixed>
     */
    public function credits(int $days): array
    {
        $since = now()->subDays($days);

        $packs = CreditPack::query()->where('status', CreditPackStatus::Active)->where('purchased_at', '>=', $since);

        return [
            'currency' => $this->ledger->currency(),
            'window_days' => $days,
            'packs_sold' => (clone $packs)->count(),
            'pack_credits_sold' => (int) (clone $packs)->sum('credits_amount'),
            'pack_revenue' => (int) (clone $packs)->sum('price_cents'),
            'credits_granted_by_plan' => (int) UsagePeriod::query()->where('starts_at', '>=', $since)->sum('credits_limit'),
            'credits_consumed' => (int) CreditTransaction::query()->where('created_at', '>=', $since)->sum('credits'),
            'credits_consumed_from_packs' => (int) CreditTransaction::query()->where('created_at', '>=', $since)->sum('topup_credits'),
            'overage_credits' => (int) CreditTransaction::query()->where('created_at', '>=', $since)->sum('overage_credits'),
            'overage_invoices' => [
                'succeeded' => OverageInvoiceAttempt::query()->where('status', OverageInvoiceAttemptStatus::Succeeded)->where('created_at', '>=', $since)->count(),
                'failed' => OverageInvoiceAttempt::query()->where('status', OverageInvoiceAttemptStatus::Failed)->where('created_at', '>=', $since)->count(),
                'billed_amount' => (int) OverageInvoiceAttempt::query()->where('status', OverageInvoiceAttemptStatus::Succeeded)->where('created_at', '>=', $since)->sum('amount_cents'),
            ],
        ];
    }

    /**
     * @return array{currency: string, top: list<array<string, mixed>>, fastest_growing: list<array<string, mixed>>}
     */
    public function topCustomers(int $limit): array
    {
        $now = CarbonImmutable::now('UTC');
        $byWorkspace = [];

        foreach ($this->ledger->payingAt($now) as $row) {
            $workspace = &$byWorkspace[$row['workspace_id']];
            $workspace ??= ['workspace_id' => $row['workspace_id'], 'name' => $row['workspace'], 'mrr' => 0, 'plans' => [], 'customer_since' => $row['billable_from']];
            $workspace['mrr'] += $row['mrr'];
            $workspace['plans'][] = $row['plan'];
            $workspace['customer_since'] = min($workspace['customer_since'], $row['billable_from']);
            unset($workspace);
        }

        $format = fn (array $workspace): array => [...$workspace, 'customer_since' => $workspace['customer_since']->toIso8601String()];

        $top = $byWorkspace;
        usort($top, fn (array $a, array $b): int => $b['mrr'] <=> $a['mrr']);

        $recent = array_filter($byWorkspace, fn (array $workspace): bool => $workspace['customer_since']->gte($now->subDays(90)));
        usort($recent, fn (array $a, array $b): int => $b['mrr'] <=> $a['mrr']);

        return [
            'currency' => $this->ledger->currency(),
            'top' => array_map($format, array_slice($top, 0, $limit)),
            'fastest_growing' => array_map($format, array_slice($recent, 0, $limit)),
        ];
    }

    /**
     * One row per paying subscription, for the CSV export.
     *
     * @return list<array<string, mixed>>
     */
    public function subscriptionRows(): array
    {
        return array_map(fn (array $row): array => [
            'subscription_id' => $row['id'],
            'workspace_id' => $row['workspace_id'],
            'workspace' => $row['workspace'],
            'plan' => $row['plan'],
            'interval' => $row['interval'],
            'status' => $row['status'],
            'mrr' => $row['mrr'],
            'billable_from' => $row['billable_from']->toIso8601String(),
            'ended_at' => $row['ended_at']?->toIso8601String(),
            'source' => $row['source'],
        ], $this->ledger->payingAt(CarbonImmutable::now('UTC')));
    }

    /**
     * The same figures the nightly snapshot stores: subscribers and MRR per plan.
     *
     * @return list<array{plan: string, subscribers: int, mrr: int}>
     */
    public function snapshotRows(): array
    {
        $rows = [];

        foreach ($this->groupByPlan($this->ledger->payingAt(CarbonImmutable::now('UTC'))) as $slug => $plan) {
            $rows[] = ['plan' => $slug, 'subscribers' => $plan['subscribers'], 'mrr' => $plan['mrr']];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{name: string, subscribers: int, mrr: int}>
     */
    private function groupByPlan(array $rows): array
    {
        $plans = [];

        foreach ($rows as $row) {
            $plans[$row['plan']]['name'] = $row['plan_name'];
            $plans[$row['plan']]['subscribers'] = ($plans[$row['plan']]['subscribers'] ?? 0) + 1;
            $plans[$row['plan']]['mrr'] = ($plans[$row['plan']]['mrr'] ?? 0) + $row['mrr'];
        }

        return $plans;
    }

    /**
     * @param  list<array<string, mixed>>  $lost
     * @return list<array{plan: string, churned_subscribers: int, churned_mrr: int}>
     */
    private function groupLost(array $lost): array
    {
        $plans = [];

        foreach ($lost as $row) {
            $plans[$row['plan']]['plan'] = $row['plan'];
            $plans[$row['plan']]['churned_subscribers'] = ($plans[$row['plan']]['churned_subscribers'] ?? 0) + 1;
            $plans[$row['plan']]['churned_mrr'] = ($plans[$row['plan']]['churned_mrr'] ?? 0) + $row['mrr'];
        }

        return array_values($plans);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hadEarlierSubscription(array $row): bool
    {
        foreach ($this->ledger->rows() as $other) {
            if ($other['workspace_id'] === $row['workspace_id'] && $other['id'] !== $row['id'] && $other['mrr'] > 0
                && $other['billable_from']->lt($row['billable_from'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function payingWorkspaceAt(array $rows, CarbonImmutable $at): bool
    {
        foreach ($rows as $row) {
            if ($row['billable_from']->lte($at) && ($row['ended_at'] === null || $row['ended_at']->gt($at))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ltvBy(string $key, string $labelKey): array
    {
        $now = CarbonImmutable::now('UTC');
        $since = $now->subDays(90);
        $groups = [];

        foreach ($this->ledger->rows() as $row) {
            if ($row['mrr'] <= 0) {
                continue;
            }

            $group = &$groups[$row[$key]];
            $group ??= ['label' => $row[$labelKey], 'paying' => 0, 'mrr' => 0, 'startingPaying' => 0, 'churned' => 0];

            if ($row['billable_from']->lte($now) && ($row['ended_at'] === null || $row['ended_at']->gt($now))) {
                $group['paying']++;
                $group['mrr'] += $row['mrr'];
            }

            if ($row['billable_from']->lte($since) && ($row['ended_at'] === null || $row['ended_at']->gt($since))) {
                $group['startingPaying']++;
            }

            if ($row['ended_at'] !== null && $row['ended_at']->between($since, $now) && $row['billable_from']->lte($row['ended_at'])) {
                $group['churned']++;
            }

            unset($group);
        }

        $result = [];

        foreach ($groups as $slug => $group) {
            $arpu = $group['paying'] === 0 ? 0 : (int) round($group['mrr'] / $group['paying']);
            $monthlyChurn = $group['startingPaying'] === 0 ? 0.0 : $group['churned'] / $group['startingPaying'] / 3;

            $result[] = [
                $key => $slug,
                'name' => $group['label'],
                'paying_subscribers' => $group['paying'],
                'arpu' => $arpu,
                'monthly_logo_churn_percent' => $group['startingPaying'] === 0 ? null : round($monthlyChurn * 100, 2),
                'estimated_ltv' => $monthlyChurn > 0 ? (int) round($arpu / $monthlyChurn) : null,
            ];
        }

        usort($result, fn (array $a, array $b): int => $b['paying_subscribers'] <=> $a['paying_subscribers']);

        return $result;
    }
}
