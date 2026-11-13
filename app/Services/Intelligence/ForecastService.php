<?php

namespace App\Services\Intelligence;

use App\Models\Billing\UsagePeriod;
use App\Models\Intelligence\Prediction;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\SpendControls;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The forward-looking numbers: provider spend, revenue and margin for the
 * coming weeks (from the daily facts, through `Forecaster`), when the
 * platform budget would be hit, and when each workspace will run out of
 * credits at its current pace. `record()` stores the day's forecasts and
 * fills in yesterday's actuals, so `accuracy()` can say how far to trust
 * them.
 */
class ForecastService
{
    public const string PROVIDER_SPEND = 'provider_spend';

    public const string REVENUE = 'revenue';

    private const int HISTORY_DAYS = 56;

    public function __construct(
        private readonly Forecaster $forecaster,
        private readonly SpendControls $controls,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function providerSpend(int $horizon = 30): array
    {
        $forecast = $this->forecastKind(self::PROVIDER_SPEND, $horizon);
        $budget = $this->controls->platformDailyBudgetUsd();
        $breach = null;

        if ($budget !== null) {
            foreach ($forecast['points'] as $point) {
                if ($point['value'] >= $budget) {
                    $breach = $point['date'];

                    break;
                }
            }
        }

        return [
            ...$forecast,
            'platform_budget_usd' => $budget,
            'budget_breach_date' => $breach,
            'next_30_days_usd' => round(array_sum(array_column(array_slice($forecast['points'], 0, 30), 'value')), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function revenue(int $horizon = 90): array
    {
        return $this->forecastKind(self::REVENUE, $horizon);
    }

    /**
     * Revenue less provider cost and card fees, day by day, from the two
     * forecasts above.
     *
     * @return array{points: list<array{date: string, revenue_usd: float, provider_cost_usd: float, gross_margin_usd: float}>, total_margin_usd: float}
     */
    public function margin(int $horizon = 30): array
    {
        $revenue = $this->forecastKind(self::REVENUE, $horizon)['points'];
        $cost = $this->forecastKind(self::PROVIDER_SPEND, $horizon)['points'];
        $fee = (float) config('billing.margin_floor.payment_fee_rate', 0);
        $points = [];

        foreach ($revenue as $index => $point) {
            $spend = $cost[$index]['value'] ?? 0.0;
            $points[] = [
                'date' => $point['date'],
                'revenue_usd' => round($point['value'], 2),
                'provider_cost_usd' => round($spend, 2),
                'gross_margin_usd' => round($point['value'] * (1 - $fee) - $spend, 2),
            ];
        }

        return ['points' => $points, 'total_margin_usd' => round(array_sum(array_column($points, 'gross_margin_usd')), 2)];
    }

    /**
     * Workspaces that will run out of credits before their period resets, at
     * the pace of their last seven days, soonest first.
     *
     * @return list<array{workspace_id: string, name: string|null, plan: string|null, credits_left: int, daily_burn: float, runs_out_on: string, period_ends_on: string, days_left: float}>
     */
    public function creditRunOut(int $limit = 50): array
    {
        $since = CarbonImmutable::today()->subDays(7);

        $burn = DB::table('fact_workspace_daily')
            ->where('date', '>=', $since->toDateString())
            ->selectRaw('workspace_id, sum(credits_used) as credits')
            ->groupBy('workspace_id')
            ->having('credits', '>', 0)
            ->pluck('credits', 'workspace_id');

        $results = [];

        foreach (Workspace::query()->whereIn('id', $burn->keys())->with('subscriptions.plan')->get() as $workspace) {
            $period = $workspace->currentUsagePeriod();
            $available = $workspace->availableCredits();

            if (! $period instanceof UsagePeriod || $available === null) {
                continue;
            }

            $daily = (float) $burn[$workspace->id] / 7;
            $days = $daily > 0 ? $available / $daily : INF;
            $runsOut = CarbonImmutable::now()->addMinutes((int) round(min($days, 3650) * 1440));

            if ($runsOut->gte($period->ends_at)) {
                continue;
            }

            $results[] = [
                'workspace_id' => $workspace->id,
                'name' => $workspace->name,
                'plan' => $workspace->currentPlan()?->slug,
                'credits_left' => $available,
                'daily_burn' => round($daily, 1),
                'runs_out_on' => $runsOut->toDateString(),
                'period_ends_on' => $period->ends_at->toDateString(),
                'days_left' => round($days, 1),
            ];
        }

        usort($results, fn (array $a, array $b): int => $a['days_left'] <=> $b['days_left']);

        return array_slice($results, 0, max(1, $limit));
    }

    /**
     * Stores today's forecasts and fills in the actual value of every past
     * prediction whose day has now been rolled up.
     *
     * @return array{recorded: int, actuals: int}
     */
    public function record(int $horizon = 30): array
    {
        $today = CarbonImmutable::today();
        $recorded = 0;

        foreach ([self::PROVIDER_SPEND, self::REVENUE] as $kind) {
            $forecast = $this->forecastKind($kind, $horizon);

            foreach ($forecast['points'] as $point) {
                Prediction::query()->updateOrCreate(
                    ['kind' => $kind, 'subject_type' => null, 'subject_id' => null, 'made_on' => $today->toDateString(), 'target_date' => $point['date']],
                    ['value' => $point['value'], 'lower' => $point['lower'], 'upper' => $point['upper'], 'inputs' => ['method' => $forecast['method'], ...$forecast['inputs']]],
                );
                $recorded++;
            }
        }

        $actuals = 0;

        Prediction::query()
            ->whereNull('actual')
            ->where('target_date', '<', $today->toDateString())
            ->whereIn('kind', [self::PROVIDER_SPEND, self::REVENUE])
            ->chunkById(500, function ($predictions) use (&$actuals): void {
                $dates = $predictions->pluck('target_date')->map(fn ($date): string => $date->toDateString())->unique()->all();
                $series = [
                    self::PROVIDER_SPEND => $this->dailyTotals(self::PROVIDER_SPEND, $dates),
                    self::REVENUE => $this->dailyTotals(self::REVENUE, $dates),
                ];

                foreach ($predictions as $prediction) {
                    $prediction->update(['actual' => $series[$prediction->kind][$prediction->target_date->toDateString()] ?? 0.0]);
                    $actuals++;
                }
            });

        return ['recorded' => $recorded, 'actuals' => $actuals];
    }

    /**
     * How far off each forecast has been: the error of forecasts made one day
     * and seven days ahead, over the last `$days` days.
     *
     * @return list<array{kind: string, horizon_days: int, samples: int, mape_percent: float|null, within_band: float|null}>
     */
    public function accuracy(int $days = 30): array
    {
        $rows = [];

        foreach ([self::PROVIDER_SPEND, self::REVENUE] as $kind) {
            foreach ([1, 7] as $horizon) {
                $predictions = Prediction::query()
                    ->where('kind', $kind)
                    ->whereNotNull('actual')
                    ->where('target_date', '>=', CarbonImmutable::today()->subDays($days)->toDateString())
                    ->get()
                    ->filter(fn (Prediction $prediction): bool => $prediction->made_on->diffInDays($prediction->target_date) == $horizon);

                $rows[] = [
                    'kind' => $kind,
                    'horizon_days' => $horizon,
                    'samples' => $predictions->count(),
                    'mape_percent' => $this->forecaster->mape($predictions->map(fn (Prediction $prediction): array => ['value' => $prediction->value, 'actual' => $prediction->actual])->values()->all()),
                    'within_band' => $predictions->isEmpty() ? null : round($predictions->filter(fn (Prediction $prediction): bool => $prediction->actual >= ($prediction->lower ?? 0) && $prediction->actual <= ($prediction->upper ?? INF))->count() / $predictions->count(), 4),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array{kind: string, method: string, history: list<array{date: string, value: float}>, points: list<array{date: string, value: float, lower: float, upper: float}>, inputs: array<string, mixed>}
     */
    private function forecastKind(string $kind, int $horizon): array
    {
        $start = CarbonImmutable::today()->subDays(self::HISTORY_DAYS);
        $end = CarbonImmutable::today()->subDay();
        $dates = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        $totals = $this->dailyTotals($kind, $dates);
        $firstActive = array_key_first(array_filter($totals, fn (float $value): bool => $value > 0));
        $history = [];

        foreach ($dates as $date) {
            if ($firstActive !== null && $date >= $firstActive) {
                $history[] = ['date' => $date, 'value' => round($totals[$date] ?? 0.0, 6)];
            }
        }

        $first = $history === [] ? CarbonImmutable::today() : CarbonImmutable::parse($history[0]['date']);
        $result = $this->forecaster->forecast(array_column($history, 'value'), max(1, min($horizon, 365)), $first->dayOfWeekIso);

        return [
            'kind' => $kind,
            'method' => $result['method'],
            'history' => $history,
            'points' => array_map(fn (array $point): array => [
                'date' => CarbonImmutable::today()->addDays($point['offset'] - 1)->toDateString(),
                'value' => $point['value'],
                'lower' => $point['lower'],
                'upper' => $point['upper'],
            ], $result['points']),
            'inputs' => $result['inputs'],
        ];
    }

    /**
     * @param  list<string>  $dates
     * @return array<string, float>
     */
    private function dailyTotals(string $kind, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $query = $kind === self::PROVIDER_SPEND
            ? DB::table('fact_usage_daily')->selectRaw('date, sum(case when is_byok then 0 else provider_cost_usd end) as total')
            : DB::table('fact_workspace_daily')->selectRaw('date, sum(revenue_usd) as total');

        return $query
            ->whereBetween('date', [min($dates), max($dates)])
            ->groupBy('date')
            ->get()
            ->mapWithKeys(fn (object $row): array => [substr((string) $row->date, 0, 10) => (float) $row->total])
            ->all();
    }
}
