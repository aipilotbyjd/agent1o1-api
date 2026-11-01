<?php

namespace App\Services\Ai\Operations;

use App\Models\Ai\ModelUsageDaily;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Carbon;

/**
 * Which models are used, by whom, and what that costs you, read from the
 * `model_usage_daily` rollup (see `RollUpModelUsage`), so it is cheap however
 * much has run. It is as fresh as the last rollup (hourly): `refreshed_at` says
 * when. Cost is tokens times the price in force when each day was last rebuilt;
 * calls served on a workspace's own key are counted but cost you nothing, and
 * a model with no price has no cost (the call counts as unpriced).
 */
class ModelUsageReport
{
    public const int MAX_DAYS = RollUpModelUsage::MAX_DAYS;

    /**
     * @return array{days: int, refreshed_at: ?Carbon, sources: array<int, string>, totals: array{calls: int, own_key_calls: int, estimated_cost_usd: float, unpriced_calls: int}, models: list<array<string, mixed>>}
     */
    public function report(int $days, ?string $workspaceId = null): array
    {
        $days = $this->clamp($days);

        $rows = ModelUsageDaily::query()
            ->where('date', '>=', $this->since($days))
            ->when($workspaceId !== null, fn ($query) => $query->where('workspace_id', $workspaceId))
            ->selectRaw('provider, model, own_key, sum(calls) as calls, sum(prompt_tokens) as prompt_tokens, sum(completion_tokens) as completion_tokens, sum(estimated_cost_usd) as cost, sum(case when estimated_cost_usd is null then calls else 0 end) as unpriced_calls')
            ->groupBy('provider', 'model', 'own_key')
            ->get();

        $models = $rows
            ->map(fn ($row): array => [
                'provider' => $row->own_key ? 'own key' : $row->provider,
                'model' => $row->model,
                'own_key' => (bool) $row->own_key,
                'calls' => (int) $row->calls,
                'prompt_tokens' => (int) $row->prompt_tokens,
                'completion_tokens' => (int) $row->completion_tokens,
                'priced' => (int) $row->unpriced_calls === 0,
                'unpriced_calls' => (int) $row->unpriced_calls,
                'estimated_cost_usd' => (int) $row->unpriced_calls === (int) $row->calls ? null : round((float) $row->cost, 6),
            ])
            ->sortByDesc(fn (array $row): array => [$row['estimated_cost_usd'] ?? -1, $row['calls']])
            ->values();

        return [
            'days' => $days,
            'refreshed_at' => ModelUsageDaily::query()->max('updated_at') === null ? null : Carbon::parse(ModelUsageDaily::query()->max('updated_at')),
            'sources' => ['workflow node runs', 'agent chat messages'],
            'totals' => [
                'calls' => $models->sum('calls'),
                'own_key_calls' => $models->where('own_key', true)->sum('calls'),
                'estimated_cost_usd' => round((float) $models->sum('estimated_cost_usd'), 6),
                'unpriced_calls' => $models->sum('unpriced_calls'),
            ],
            'models' => $models->all(),
        ];
    }

    /**
     * The workspaces that cost you the most over the last `$days`.
     *
     * @return list<array{workspace_id: string, name: ?string, calls: int, estimated_cost_usd: float, unpriced_calls: int}>
     */
    public function topWorkspaces(int $days, int $limit = 10): array
    {
        $rows = ModelUsageDaily::query()
            ->where('date', '>=', $this->since($this->clamp($days)))
            ->where('own_key', false)
            ->selectRaw('workspace_id, sum(calls) as calls, sum(estimated_cost_usd) as cost, sum(case when estimated_cost_usd is null then calls else 0 end) as unpriced_calls')
            ->groupBy('workspace_id')
            ->orderByDesc('cost')
            ->limit($limit)
            ->get();

        $names = Workspace::query()->whereIn('id', $rows->pluck('workspace_id'))->pluck('name', 'id');

        return $rows->map(fn ($row): array => [
            'workspace_id' => $row->workspace_id,
            'name' => $names[$row->workspace_id] ?? null,
            'calls' => (int) $row->calls,
            'estimated_cost_usd' => round((float) $row->cost, 6),
            'unpriced_calls' => (int) $row->unpriced_calls,
        ])->all();
    }

    /**
     * One entry per day, oldest first, with days nothing ran filled in as zero.
     *
     * @return list<array{date: string, calls: int, estimated_cost_usd: float}>
     */
    public function daily(int $days): array
    {
        $days = $this->clamp($days);

        $totals = ModelUsageDaily::query()
            ->where('date', '>=', $this->since($days))
            ->selectRaw('date, sum(calls) as calls, sum(estimated_cost_usd) as cost')
            ->groupBy('date')
            ->get()
            ->mapWithKeys(fn ($row): array => [Carbon::parse($row->date)->toDateString() => $row]);

        return collect(range($days - 1, 0))->map(function (int $offset) use ($totals): array {
            $date = today()->subDays($offset)->toDateString();

            return [
                'date' => $date,
                'calls' => (int) ($totals[$date]->calls ?? 0),
                'estimated_cost_usd' => round((float) ($totals[$date]->cost ?? 0), 6),
            ];
        })->all();
    }

    private function clamp(int $days): int
    {
        return max(1, min($days, self::MAX_DAYS));
    }

    private function since(int $days): string
    {
        return today()->subDays($days - 1)->toDateString();
    }
}
