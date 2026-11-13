<?php

namespace App\Services\Intelligence;

use App\Enums\Billing\CreditPackStatus;
use App\Models\Ai\ModelRoute;
use App\Models\Billing\CreditPack;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\SpendControls;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ranked lists of customers that need a decision, each with a score from 0 to
 * 100 and the factors behind it, so a score is never a black box:
 *
 * - margin at risk: projected to cost more this period than they pay;
 * - churn risk: paying customers whose activity, success rate or payments
 *   are slipping;
 * - upgrade likelihood: customers pressing against their allowance;
 * - abuse: free and trial workspaces whose usage looks like abuse.
 *
 * The weights are simple, stated rules rather than a trained model; they are
 * the starting point a trained model would have to beat.
 */
class RiskScores
{
    public function __construct(private readonly SpendControls $controls) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function marginAtRisk(int $limit = 25): array
    {
        $fee = (float) config('billing.margin_floor.payment_fee_rate', 0);
        $results = [];

        foreach ($this->activeWorkspaces(7) as $workspace) {
            $period = $workspace->currentUsagePeriod();
            $elapsed = max(1, $period->starts_at->diffInHours(now()));
            $length = max(1, $period->starts_at->diffInHours($period->ends_at));

            $cost = (float) DB::table('ai_call_events')
                ->where('workspace_id', $workspace->id)
                ->where('occurred_at', '>=', $period->starts_at)
                ->where('is_byok', false)
                ->sum('provider_cost_usd');

            $revenue = (float) DB::table('credit_transactions')
                ->where('workspace_id', $workspace->id)
                ->where('created_at', '>=', $period->starts_at)
                ->sum('revenue_usd');

            $projectedCost = $cost / $elapsed * $length;
            $projectedRevenue = $revenue / $elapsed * $length * (1 - $fee);
            $gap = $projectedCost - $projectedRevenue;

            if ($gap <= 0 || $projectedCost < 1) {
                continue;
            }

            $results[] = [
                'workspace_id' => $workspace->id,
                'name' => $workspace->name,
                'plan' => $workspace->currentPlan()?->slug,
                'score' => (int) min(100, round($gap / max($projectedCost, 0.01) * 100)),
                'projected_cost_usd' => round($projectedCost, 2),
                'projected_revenue_usd' => round($projectedRevenue, 2),
                'projected_loss_usd' => round($gap, 2),
                'period_ends_on' => $period->ends_at->toDateString(),
                'factors' => $this->topModels($workspace->id, $period->starts_at),
            ];
        }

        return $this->ranked($results, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function churnRisk(int $limit = 25): array
    {
        $now = CarbonImmutable::now();
        $results = [];

        foreach ($this->payingWorkspaces() as $workspace) {
            $recentRuns = Run::query()->where('workspace_id', $workspace->id)->where('created_at', '>=', $now->subDays(7))->count();
            $earlierRuns = Run::query()->where('workspace_id', $workspace->id)->whereBetween('created_at', [$now->subDays(28), $now->subDays(7)])->count() / 3;
            $calls = DB::table('fact_usage_daily')->where('workspace_id', $workspace->id)->where('date', '>=', $now->subDays(7)->toDateString())->selectRaw('sum(calls) as calls, sum(failed_calls) as failed')->first();
            $failureRate = (int) ($calls->calls ?? 0) > 0 ? (int) $calls->failed / (int) $calls->calls : 0.0;
            $dunning = (bool) $workspace->subscription('default')?->inDunning();

            $factors = [];
            $score = 0;

            if ($earlierRuns >= 3 && $recentRuns < $earlierRuns * 0.5) {
                $drop = 1 - $recentRuns / $earlierRuns;
                $score += (int) round(45 * $drop);
                $factors[] = sprintf('Runs fell %d%% (%d this week, %.0f a week before)', round($drop * 100), $recentRuns, $earlierRuns);
            }

            if ($failureRate > 0.1) {
                $score += (int) round(min(1, $failureRate * 2) * 25);
                $factors[] = sprintf('%d%% of AI calls failed this week', round($failureRate * 100));
            }

            if ($dunning) {
                $score += 30;
                $factors[] = 'Payment is failing';
            }

            if ($score >= 20) {
                $results[] = [
                    'workspace_id' => $workspace->id,
                    'name' => $workspace->name,
                    'plan' => $workspace->currentPlan()?->slug,
                    'score' => min(100, $score),
                    'factors' => $factors,
                ];
            }
        }

        return $this->ranked($results, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function upgradeLikelihood(int $limit = 25): array
    {
        $results = [];

        foreach ($this->activeWorkspaces(14) as $workspace) {
            $period = $workspace->currentUsagePeriod();

            if ($period->credits_limit === null || $period->credits_limit <= 0) {
                continue;
            }

            $usage = $period->credits_used / $period->credits_limit;
            $elapsed = max(0.05, $period->starts_at->diffInHours(now()) / max(1, $period->starts_at->diffInHours($period->ends_at)));
            $packs = CreditPack::query()->where('workspace_id', $workspace->id)->where('status', CreditPackStatus::Active)->where('created_at', '>=', now()->subDays(60))->count();

            $factors = [];
            $score = 0;

            if ($usage >= 0.6) {
                $score += (int) round(min(1, $usage) * 40);
                $factors[] = sprintf('Used %d%% of this period\'s credits', round($usage * 100));
            }

            if ($usage / $elapsed >= 1.2) {
                $score += 25;
                $factors[] = sprintf('On pace to use %d%% of the allowance', round(min(9.99, $usage / $elapsed) * 100));
            }

            if ($packs > 0) {
                $score += min(35, $packs * 15);
                $factors[] = "Bought {$packs} credit pack(s) in 60 days";
            }

            if ($score >= 30) {
                $results[] = [
                    'workspace_id' => $workspace->id,
                    'name' => $workspace->name,
                    'plan' => $workspace->currentPlan()?->slug,
                    'score' => min(100, $score),
                    'factors' => $factors,
                ];
            }
        }

        return $this->ranked($results, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function abuse(int $limit = 25): array
    {
        $tiers = ModelRoute::query()
            ->join('model_catalog', 'model_catalog.id', '=', 'model_routes.model_catalog_id')
            ->get(['model_routes.execution_provider', 'model_routes.execution_model_id', 'model_catalog.cost_tier'])
            ->mapWithKeys(fn ($row): array => ["{$row->execution_provider}:{$row->execution_model_id}" => (int) $row->cost_tier]);

        $paying = $this->payingWorkspaces()->pluck('id')->all();
        $since = now()->subDays(3);

        $spend = DB::table('ai_call_events')
            ->where('occurred_at', '>=', $since)
            ->whereNotNull('workspace_id')
            ->whereNotIn('workspace_id', $paying)
            ->where('is_byok', false)
            ->selectRaw('workspace_id, provider, model, count(*) as calls, sum(provider_cost_usd) as cost')
            ->groupBy('workspace_id', 'provider', 'model')
            ->get()
            ->groupBy('workspace_id');

        $workspaces = Workspace::query()->whereIn('id', $spend->keys())->with('subscriptions.plan')->get()->keyBy('id');
        $results = [];

        foreach ($spend as $workspaceId => $rows) {
            $workspace = $workspaces[$workspaceId] ?? null;

            if ($workspace === null) {
                continue;
            }

            $cost = (float) $rows->sum('cost');
            $frontierCost = (float) $rows->filter(fn ($row): bool => ($tiers["{$row->provider}:{$row->model}"] ?? 1) >= 2)->sum('cost');
            $factors = [];
            $score = 0;

            if ($workspace->created_at?->gt(now()->subDays(7))) {
                $score += 20;
                $factors[] = 'Created in the last 7 days';
            }

            if ($cost > 0 && $frontierCost / $cost > 0.5) {
                $score += 25;
                $factors[] = sprintf('%d%% of spend on premium models', round($frontierCost / $cost * 100));
            }

            if ($cost >= (float) config('intelligence.abuse.free_spend_usd', 2)) {
                $score += (int) min(35, round($cost / max(0.01, (float) config('intelligence.abuse.free_spend_usd', 2)) * 15));
                $factors[] = sprintf('$%.2f of provider cost in 3 days without paying', $cost);
            }

            if ($this->controls->throttledUntil($workspaceId) !== null) {
                $score += 20;
                $factors[] = 'Already throttled';
            }

            if ($score >= 40) {
                $results[] = [
                    'workspace_id' => $workspaceId,
                    'name' => $workspace->name,
                    'score' => min(100, $score),
                    'provider_cost_usd' => round($cost, 2),
                    'factors' => $factors,
                ];
            }
        }

        return $this->ranked($results, $limit);
    }

    /**
     * @return Collection<int, Workspace>
     */
    private function activeWorkspaces(int $days): Collection
    {
        $ids = DB::table('fact_workspace_daily')
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->where(fn ($query) => $query->where('credits_used', '>', 0)->orWhere('ai_calls', '>', 0))
            ->distinct()
            ->pluck('workspace_id');

        return Workspace::query()->whereIn('id', $ids)->with('subscriptions.plan')->get();
    }

    /**
     * @return Collection<int, Workspace>
     */
    private function payingWorkspaces(): Collection
    {
        $ids = DB::table('fact_workspace_daily')
            ->where('date', '>=', now()->subDays(35)->toDateString())
            ->where('is_paying', true)
            ->distinct()
            ->pluck('workspace_id');

        return Workspace::query()->whereIn('id', $ids)->with('subscriptions.plan')->get();
    }

    /**
     * @return list<string>
     */
    private function topModels(string $workspaceId, CarbonInterface $since): array
    {
        return DB::table('ai_call_events')
            ->where('workspace_id', $workspaceId)
            ->where('occurred_at', '>=', $since)
            ->where('is_byok', false)
            ->selectRaw('provider, model, sum(provider_cost_usd) as cost')
            ->groupBy('provider', 'model')
            ->orderByDesc('cost')
            ->limit(3)
            ->get()
            ->map(fn ($row): string => sprintf('$%.2f on %s:%s', $row->cost, $row->provider, $row->model))
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return list<array<string, mixed>>
     */
    private function ranked(array $results, int $limit): array
    {
        usort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, max(1, $limit));
    }
}
