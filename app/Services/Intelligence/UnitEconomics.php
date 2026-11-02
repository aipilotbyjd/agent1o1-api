<?php

namespace App\Services\Intelligence;

use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Whether the business makes money on AI, and where it does not: revenue
 * against provider cost by day, plan, model, feature and customer, from the
 * daily fact tables (`FactRollup`). Revenue is what credits earned
 * (`credit_transactions.revenue_usd`), cost is what providers charged the
 * platform (`ai_call_events`), and the card fee comes off revenue before
 * margin is worked out.
 */
class UnitEconomics
{
    /**
     * @return array<string, mixed>
     */
    public function overview(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $current = $this->totals($from, $to);
        $length = $from->diffInDays($to) + 1;
        $previous = $this->totals($from->subDays($length), $from->subDay());

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            ...$current,
            'previous' => $previous,
            'change' => [
                'revenue_usd' => $this->change($current['revenue_usd'], $previous['revenue_usd']),
                'provider_cost_usd' => $this->change($current['provider_cost_usd'], $previous['provider_cost_usd']),
                'gross_margin_usd' => $this->change($current['gross_margin_usd'], $previous['gross_margin_usd']),
            ],
        ];
    }

    /**
     * @return list<array{date: string, revenue_usd: float, provider_cost_usd: float, gross_margin_usd: float, credits_used: int}>
     */
    public function timeseries(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('fact_workspace_daily')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('date, sum(revenue_usd) as revenue, sum(provider_cost_usd) as cost, sum(credits_used) as credits')
            ->groupBy('date')
            ->get()
            ->keyBy(fn (object $row): string => substr((string) $row->date, 0, 10));

        $series = [];

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $row = $rows[$day->toDateString()] ?? null;
            $revenue = (float) ($row->revenue ?? 0);
            $cost = (float) ($row->cost ?? 0);

            $series[] = [
                'date' => $day->toDateString(),
                'revenue_usd' => round($revenue, 2),
                'provider_cost_usd' => round($cost, 2),
                'gross_margin_usd' => round($this->netRevenue($revenue) - $cost, 2),
                'credits_used' => (int) ($row->credits ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byPlan(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('fact_workspace_daily')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("coalesce(plan_slug, 'none') as plan, count(distinct workspace_id) as workspaces, sum(credits_used) as credits, sum(revenue_usd) as revenue, sum(provider_cost_usd) as cost")
            ->groupBy('plan')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn (object $row): array => [
                'plan' => $row->plan,
                'workspaces' => (int) $row->workspaces,
                'credits_used' => (int) $row->credits,
                ...$this->money((float) $row->revenue, (float) $row->cost),
            ])
            ->all();
    }

    /**
     * Spend, volume, reliability and prompt-cache use per backend model.
     *
     * @return list<array<string, mixed>>
     */
    public function byModel(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('fact_usage_daily')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('provider, model, sum(calls) as calls, sum(failed_calls) as failed, sum(input_tokens) as input, sum(cache_read_tokens) as cached, sum(output_tokens) as output,
                sum(case when is_byok then 0 else provider_cost_usd end) as cost, sum(untrusted_cost_usd) as untrusted, avg(avg_latency_ms) as latency, sum(case when is_byok then calls else 0 end) as byok_calls')
            ->groupBy('provider', 'model')
            ->orderByDesc('cost')
            ->get()
            ->map(fn (object $row): array => [
                'provider' => $row->provider,
                'model' => $row->model,
                'calls' => (int) $row->calls,
                'byok_calls' => (int) $row->byok_calls,
                'failure_rate' => $this->ratio((int) $row->failed, (int) $row->calls),
                'input_tokens' => (int) $row->input,
                'output_tokens' => (int) $row->output,
                'cache_hit_rate' => $this->ratio((int) $row->cached, (int) $row->input),
                'provider_cost_usd' => round((float) $row->cost, 2),
                'untrusted_cost_usd' => round((float) $row->untrusted, 2),
                'avg_latency_ms' => $row->latency === null ? null : (int) round((float) $row->latency),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byFeature(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('fact_usage_daily')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('feature, sum(calls) as calls, sum(failed_calls) as failed, sum(case when is_byok then 0 else provider_cost_usd end) as cost, sum(input_tokens) as input, sum(output_tokens) as output')
            ->groupBy('feature')
            ->orderByDesc('cost')
            ->get()
            ->map(fn (object $row): array => [
                'feature' => $row->feature,
                'calls' => (int) $row->calls,
                'failure_rate' => $this->ratio((int) $row->failed, (int) $row->calls),
                'input_tokens' => (int) $row->input,
                'output_tokens' => (int) $row->output,
                'provider_cost_usd' => round((float) $row->cost, 2),
            ])
            ->all();
    }

    /**
     * Workspaces ranked by margin, worst first: the customers who cost more
     * than they pay.
     *
     * @return list<array<string, mixed>>
     */
    public function customers(CarbonImmutable $from, CarbonImmutable $to, int $limit = 20, bool $lossesOnly = true): array
    {
        $rows = DB::table('fact_workspace_daily')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('workspace_id, max(plan_slug) as plan, max(case when is_paying then 1 else 0 end) as paying, sum(credits_used) as credits, sum(revenue_usd) as revenue, sum(provider_cost_usd) as cost, sum(ai_calls) as calls')
            ->groupBy('workspace_id')
            ->when($lossesOnly, fn ($query) => $query->havingRaw('sum(revenue_usd) < sum(provider_cost_usd)'))
            ->orderByRaw('sum(revenue_usd) - sum(provider_cost_usd) asc')
            ->limit(max(1, min($limit, 200)))
            ->get();

        $names = Workspace::query()->whereIn('id', $rows->pluck('workspace_id'))->pluck('name', 'id');

        return $rows->map(fn (object $row): array => [
            'workspace_id' => $row->workspace_id,
            'name' => $names[$row->workspace_id] ?? null,
            'plan' => $row->plan,
            'paying' => (bool) $row->paying,
            'credits_used' => (int) $row->credits,
            'ai_calls' => (int) $row->calls,
            ...$this->money((float) $row->revenue, (float) $row->cost),
        ])->all();
    }

    /**
     * One customer's profit and loss by month.
     *
     * @return array{workspace_id: string, months: list<array<string, mixed>>, models: list<array<string, mixed>>}
     */
    public function customerPnl(string $workspaceId, int $months = 6): array
    {
        $from = CarbonImmutable::today()->startOfMonth()->subMonths(max(1, $months) - 1);

        $byMonth = DB::table('fact_workspace_daily')
            ->where('workspace_id', $workspaceId)
            ->where('date', '>=', $from->toDateString())
            ->get(['date', 'revenue_usd', 'provider_cost_usd', 'credits_used'])
            ->groupBy(fn (object $row): string => substr((string) $row->date, 0, 7));

        $monthRows = [];

        for ($month = $from; $month->lte(CarbonImmutable::today()); $month = $month->addMonth()) {
            $rows = $byMonth[$month->format('Y-m')] ?? collect();

            $monthRows[] = [
                'month' => $month->format('Y-m'),
                'credits_used' => (int) $rows->sum('credits_used'),
                ...$this->money((float) $rows->sum('revenue_usd'), (float) $rows->sum('provider_cost_usd')),
            ];
        }

        $models = DB::table('fact_usage_daily')
            ->where('workspace_id', $workspaceId)
            ->where('date', '>=', $from->toDateString())
            ->selectRaw('feature, provider, model, sum(calls) as calls, sum(case when is_byok then 0 else provider_cost_usd end) as cost')
            ->groupBy('feature', 'provider', 'model')
            ->orderByDesc('cost')
            ->limit(25)
            ->get()
            ->map(fn (object $row): array => [
                'feature' => $row->feature,
                'provider' => $row->provider,
                'model' => $row->model,
                'calls' => (int) $row->calls,
                'provider_cost_usd' => round((float) $row->cost, 2),
            ])
            ->all();

        return ['workspace_id' => $workspaceId, 'months' => $monthRows, 'models' => $models];
    }

    /**
     * How much of their allowance customers on each plan actually use in
     * the current billing period: the share of credits granted but never
     * spent is what makes a generous plan affordable.
     *
     * @return list<array{plan: string, periods: int, p50: float, p90: float, p99: float, unused_share: float}>
     */
    public function creditUtilization(): array
    {
        $periods = UsagePeriod::query()
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->whereNotNull('credits_limit')
            ->where('credits_limit', '>', 0)
            ->with('workspace')
            ->get(['id', 'workspace_id', 'credits_used', 'credits_limit']);

        return $periods
            ->groupBy(fn (UsagePeriod $period): string => $period->workspace?->currentPlan()?->slug ?? 'none')
            ->map(function (Collection $group, string $plan): array {
                $ratios = $group->map(fn (UsagePeriod $period): float => min(1.0, $period->credits_used / $period->credits_limit))->sort()->values()->all();
                $granted = (int) $group->sum('credits_limit');
                $used = (int) $group->sum(fn (UsagePeriod $period): int => min($period->credits_used, $period->credits_limit));

                return [
                    'plan' => $plan,
                    'periods' => count($ratios),
                    'p50' => $this->percentile($ratios, 0.5),
                    'p90' => $this->percentile($ratios, 0.9),
                    'p99' => $this->percentile($ratios, 0.99),
                    'unused_share' => $granted > 0 ? round(1 - $used / $granted, 4) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, float|int>
     */
    private function totals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $workspaces = DB::table('fact_workspace_daily')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('sum(revenue_usd) as revenue, sum(provider_cost_usd) as cost, sum(credits_used) as credits,
                sum(case when is_paying then 0 else provider_cost_usd end) as free_cost,
                count(distinct case when is_paying then workspace_id end) as paying,
                count(distinct workspace_id) as active')
            ->first();

        $usage = DB::table('fact_usage_daily')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('sum(calls) as calls, sum(failed_calls) as failed, sum(untrusted_cost_usd) as untrusted, sum(case when is_byok then 0 else provider_cost_usd end) as cost')
            ->first();

        $revenue = (float) ($workspaces->revenue ?? 0);
        $cost = (float) ($workspaces->cost ?? 0);

        return [
            ...$this->money($revenue, $cost),
            'credits_used' => (int) ($workspaces->credits ?? 0),
            'free_and_trial_cost_usd' => round((float) ($workspaces->free_cost ?? 0), 2),
            'paying_workspaces' => (int) ($workspaces->paying ?? 0),
            'active_workspaces' => (int) ($workspaces->active ?? 0),
            'ai_calls' => (int) ($usage->calls ?? 0),
            'failure_rate' => $this->ratio((int) ($usage->failed ?? 0), (int) ($usage->calls ?? 0)),
            'untrusted_price_share' => (float) ($usage->cost ?? 0) > 0 ? round((float) $usage->untrusted / (float) $usage->cost, 4) : 0.0,
        ];
    }

    /**
     * @return array{revenue_usd: float, payment_fees_usd: float, provider_cost_usd: float, gross_margin_usd: float, gross_margin_percent: float|null, ai_cost_ratio: float|null}
     */
    private function money(float $revenue, float $cost): array
    {
        $net = $this->netRevenue($revenue);
        $margin = $net - $cost;

        return [
            'revenue_usd' => round($revenue, 2),
            'payment_fees_usd' => round($revenue - $net, 2),
            'provider_cost_usd' => round($cost, 2),
            'gross_margin_usd' => round($margin, 2),
            'gross_margin_percent' => $revenue > 0 ? round($margin / $revenue * 100, 1) : null,
            'ai_cost_ratio' => $revenue > 0 ? round($cost / $revenue, 4) : null,
        ];
    }

    private function netRevenue(float $revenue): float
    {
        return $revenue * (1 - (float) config('billing.margin_floor.payment_fee_rate', 0));
    }

    private function ratio(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole, 4) : 0.0;
    }

    private function change(float $current, float $previous): ?float
    {
        return $previous != 0.0 ? round(($current - $previous) / abs($previous) * 100, 1) : null;
    }

    /**
     * @param  list<float>  $sorted
     */
    private function percentile(array $sorted, float $quantile): float
    {
        if ($sorted === []) {
            return 0.0;
        }

        $index = (int) ceil($quantile * count($sorted)) - 1;

        return round($sorted[max(0, min($index, count($sorted) - 1))], 4);
    }
}
