<?php

namespace App\Services\Intelligence;

use App\Enums\Billing\PlanGrantSource;
use App\Models\Workspaces\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the daily fact tables for the latest days: `fact_usage_daily` from
 * the AI call ledger, and `fact_workspace_daily` from the credit ledger joined
 * with each workspace's provider cost.
 *
 * A day is rebuilt whole, in one transaction, rather than added to, so
 * running it twice is harmless and a late-arriving charge is picked up the
 * next time its day is rebuilt.
 */
class FactRollup
{
    public const int MAX_DAYS = 400;

    private const int INSERT_CHUNK = 500;

    /**
     * @return array{days: int, usage_rows: int, workspace_rows: int}
     */
    public function run(int $days = 2): array
    {
        $days = max(1, min($days, self::MAX_DAYS));
        $usageRows = 0;
        $workspaceRows = 0;

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day = CarbonImmutable::today()->subDays($offset);
            $usageRows += $this->rollUpUsage($day);
            $workspaceRows += $this->rollUpWorkspaces($day);
        }

        return ['days' => $days, 'usage_rows' => $usageRows, 'workspace_rows' => $workspaceRows];
    }

    private function rollUpUsage(CarbonImmutable $day): int
    {
        $rows = DB::table('ai_call_events')
            ->where('occurred_at', '>=', $day)
            ->where('occurred_at', '<', $day->addDay())
            ->selectRaw("workspace_id, feature, provider, model, is_byok,
                count(*) as calls,
                sum(case when status = 'ok' then 0 else 1 end) as failed_calls,
                sum(input_tokens) as input_tokens,
                sum(cache_read_tokens) as cache_read_tokens,
                sum(output_tokens) as output_tokens,
                sum(provider_cost_usd) as provider_cost_usd,
                sum(case when price_status in ('verified', 'byok', 'none') then 0 else provider_cost_usd end) as untrusted_cost_usd,
                avg(latency_ms) as avg_latency_ms")
            ->groupBy('workspace_id', 'feature', 'provider', 'model', 'is_byok')
            ->get()
            ->map(fn (object $row): array => [
                'date' => $day->toDateString(),
                'workspace_id' => $row->workspace_id,
                'feature' => $row->feature,
                'provider' => $row->provider,
                'model' => $row->model,
                'is_byok' => (bool) $row->is_byok,
                'calls' => (int) $row->calls,
                'failed_calls' => (int) $row->failed_calls,
                'input_tokens' => (int) $row->input_tokens,
                'cache_read_tokens' => (int) $row->cache_read_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'provider_cost_usd' => round((float) $row->provider_cost_usd, 6),
                'untrusted_cost_usd' => round((float) $row->untrusted_cost_usd, 6),
                'avg_latency_ms' => $row->avg_latency_ms === null ? null : (int) round((float) $row->avg_latency_ms),
            ])
            ->all();

        DB::transaction(function () use ($day, $rows): void {
            DB::table('fact_usage_daily')->where('date', $day->toDateString())->delete();

            foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                DB::table('fact_usage_daily')->insert($chunk);
            }
        });

        return count($rows);
    }

    private function rollUpWorkspaces(CarbonImmutable $day): int
    {
        $start = $day;
        $end = $day->addDay();

        $credits = DB::table('credit_transactions')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw('workspace_id, sum(credits) as credits, sum(revenue_usd) as revenue')
            ->groupBy('workspace_id')
            ->get()
            ->keyBy('workspace_id');

        $calls = DB::table('ai_call_events')
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->whereNotNull('workspace_id')
            ->selectRaw("workspace_id, count(*) as calls,
                sum(case when status = 'ok' then 0 else 1 end) as failed,
                sum(case when is_byok then 0 else provider_cost_usd end) as cost")
            ->groupBy('workspace_id')
            ->get()
            ->keyBy('workspace_id');

        $ids = $credits->keys()->merge($calls->keys())->unique()->values();
        $plans = $this->plansOf($ids->all());

        $rows = $ids->map(function (string $workspaceId) use ($day, $credits, $calls, $plans): array {
            $revenue = (float) ($credits[$workspaceId]->revenue ?? 0);
            $cost = (float) ($calls[$workspaceId]->cost ?? 0);

            return [
                'date' => $day->toDateString(),
                'workspace_id' => $workspaceId,
                'plan_slug' => $plans[$workspaceId]['slug'] ?? null,
                'is_paying' => $plans[$workspaceId]['paying'] ?? false,
                'credits_used' => (int) ($credits[$workspaceId]->credits ?? 0),
                'revenue_usd' => round($revenue, 6),
                'provider_cost_usd' => round($cost, 6),
                'margin_usd' => round($revenue - $cost, 6),
                'ai_calls' => (int) ($calls[$workspaceId]->calls ?? 0),
                'failed_calls' => (int) ($calls[$workspaceId]->failed ?? 0),
            ];
        })->all();

        DB::transaction(function () use ($day, $rows): void {
            DB::table('fact_workspace_daily')->where('date', $day->toDateString())->delete();

            foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                DB::table('fact_workspace_daily')->insert($chunk);
            }
        });

        return count($rows);
    }

    /**
     * Each workspace's current plan, and whether it pays for it (a valid
     * subscription or a lifetime purchase). Today's plan stands in for the
     * day being rebuilt: plan history is in the subscription ledger, but the
     * facts only need it to group spend.
     *
     * @param  list<string>  $workspaceIds
     * @return array<string, array{slug: string|null, paying: bool}>
     */
    private function plansOf(array $workspaceIds): array
    {
        $plans = [];

        foreach (array_chunk($workspaceIds, 200) as $chunk) {
            foreach (Workspace::query()->whereIn('id', $chunk)->get() as $workspace) {
                $grant = $workspace->activePlanGrant();

                $plans[$workspace->id] = [
                    'slug' => $workspace->currentPlan()?->slug,
                    'paying' => $workspace->activeSubscription() !== null || $grant?->source === PlanGrantSource::LifetimePurchase,
                ];
            }
        }

        return $plans;
    }
}
