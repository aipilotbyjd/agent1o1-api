<?php

namespace App\Services\Intelligence;

use App\Models\Ai\AiCallEvent;
use App\Models\Billing\CreditReservation;
use App\Services\Billing\SpendControls;
use Illuminate\Support\Facades\DB;

/**
 * What is happening right now, straight from the call ledger: today's
 * provider spend against the platform budget, spend minute by minute over the
 * last hour, and each provider route's health (success rate, latency
 * percentiles, failover rate) with its SLO status.
 */
class LiveOperations
{
    public function __construct(private readonly SpendControls $controls) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $budget = $this->controls->platformDailyBudgetUsd();
        $spent = (float) AiCallEvent::query()->where('occurred_at', '>=', now()->startOfDay())->where('is_byok', false)->sum('provider_cost_usd');

        return [
            'at' => now()->toIso8601String(),
            'kill_switch' => $this->controls->killSwitch()->value,
            'spend_today_usd' => round($spent, 4),
            'platform_budget_usd' => $budget,
            'budget_used' => $budget !== null ? round($spent / $budget, 4) : null,
            'hard_stop_usd' => $budget !== null ? round($budget * $this->controls->hardStopRatio(), 2) : null,
            'credits_held' => (int) CreditReservation::query()->outstanding()->sum('credits'),
            'per_minute' => $this->perMinute(),
            'providers' => $this->providerHealth(),
        ];
    }

    /**
     * Calls and spend in each of the last 60 minutes.
     *
     * @return list<array{minute: string, calls: int, cost_usd: float}>
     */
    public function perMinute(): array
    {
        $since = now()->subMinutes(59)->startOfMinute();

        $events = AiCallEvent::query()
            ->where('occurred_at', '>=', $since)
            ->get(['occurred_at', 'provider_cost_usd', 'is_byok'])
            ->groupBy(fn (AiCallEvent $event): string => $event->occurred_at->format('Y-m-d H:i'));

        $series = [];

        for ($minute = $since->copy(); $minute->lte(now()); $minute->addMinute()) {
            $bucket = $events[$minute->format('Y-m-d H:i')] ?? collect();

            $series[] = [
                'minute' => $minute->format('H:i'),
                'calls' => $bucket->count(),
                'cost_usd' => round((float) $bucket->where('is_byok', false)->sum('provider_cost_usd'), 4),
            ];
        }

        return $series;
    }

    /**
     * Each provider and model's last hour against its SLO
     * (`model_operations.slo`).
     *
     * @return list<array<string, mixed>>
     */
    public function providerHealth(int $minutes = 60): array
    {
        $successTarget = (float) config('model_operations.slo.success_rate', 0.99);
        $latencyTarget = (int) config('model_operations.slo.p95_latency_ms', 20000);

        return AiCallEvent::query()
            ->where('occurred_at', '>=', now()->subMinutes($minutes))
            ->get(['provider', 'model', 'status', 'latency_ms', 'provider_cost_usd'])
            ->groupBy(fn (AiCallEvent $event): string => "{$event->provider}:{$event->model}")
            ->map(function ($events, string $key) use ($successTarget, $latencyTarget): array {
                [$provider, $model] = explode(':', $key, 2);
                $calls = $events->count();
                $ok = $events->where('status', AiCallEvent::STATUS_OK)->count();
                $latencies = $events->pluck('latency_ms')->filter()->sort()->values()->all();
                $p95 = $this->percentile($latencies, 0.95);
                $successRate = $calls > 0 ? round($ok / $calls, 4) : 1.0;

                return [
                    'provider' => $provider,
                    'model' => $model,
                    'calls' => $calls,
                    'success_rate' => $successRate,
                    'failover_rate' => $calls > 0 ? round($events->where('status', AiCallEvent::STATUS_FAILED_OVER)->count() / $calls, 4) : 0.0,
                    'p50_latency_ms' => $this->percentile($latencies, 0.5),
                    'p95_latency_ms' => $p95,
                    'cost_usd' => round((float) $events->sum('provider_cost_usd'), 4),
                    'slo' => match (true) {
                        $calls < 5 => 'not_enough_data',
                        $successRate < $successTarget || ($p95 !== null && $p95 > $latencyTarget) => 'breached',
                        default => 'met',
                    },
                ];
            })
            ->sortByDesc('calls')
            ->values()
            ->all();
    }

    /**
     * Every call, charge and hold that belongs to one trace, in order: what
     * one run or request actually did and cost.
     *
     * @return array{trace_id: string, calls: list<array<string, mixed>>, total_cost_usd: float, charges: list<array<string, mixed>>}
     */
    public function trace(string $traceId): array
    {
        $calls = AiCallEvent::query()->where('trace_id', $traceId)->orderBy('occurred_at')->orderBy('step')->get();

        $subjects = $calls->pluck('subject_id')->filter()->unique()->values();
        $runs = $calls->pluck('run_id')->filter()->unique()->values();

        $charges = DB::table('credit_transactions')
            ->where(fn ($query) => $query->whereIn('source_id', $subjects)->orWhereIn('source_id', $runs))
            ->orderBy('created_at')
            ->get(['id', 'source_type', 'source_id', 'credits', 'revenue_usd', 'reason', 'created_at'])
            ->map(fn (object $row): array => (array) $row)
            ->all();

        return [
            'trace_id' => $traceId,
            'calls' => $calls->map(fn (AiCallEvent $event): array => $event->toArray())->all(),
            'total_cost_usd' => round((float) $calls->sum('provider_cost_usd'), 6),
            'charges' => $charges,
        ];
    }

    /**
     * @param  list<int>  $sorted
     */
    private function percentile(array $sorted, float $quantile): ?int
    {
        if ($sorted === []) {
            return null;
        }

        return (int) $sorted[max(0, min((int) ceil($quantile * count($sorted)) - 1, count($sorted) - 1))];
    }
}
