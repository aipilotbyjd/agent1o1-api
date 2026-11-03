<?php

namespace App\Services\Intelligence;

use App\Models\Intelligence\Experiment;
use App\Models\Intelligence\ExperimentAssignment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A/B tests on what the business can change without code: trial credits,
 * trial length, pack prices, pricing page copy. Each experiment has weighted
 * variants (each carrying its own `settings`); a workspace is assigned once,
 * deterministically from its id, so it always sees the same variant.
 *
 * `results()` compares the variants on conversion to paying and on gross
 * margin per workspace from the daily facts, and `guard()` stops an
 * experiment whose variant falls below its guard threshold (a variant's
 * gross margin per workspace under the threshold, in dollars), so a losing
 * variant cannot run on unnoticed.
 *
 * Product code reads a setting with `setting('trial-credits', $workspaceId,
 * 'credits', $default)`, which returns the default whenever the experiment
 * is not running.
 */
class Experiments
{
    /**
     * @param  array{key: string, name: string, hypothesis?: string|null, variants: list<array{key: string, weight: int, settings?: array<string, mixed>}>, guard_metric?: string, guard_threshold?: float|null}  $data
     */
    public function create(array $data): Experiment
    {
        $variants = array_values($data['variants']);

        if (count($variants) < 2 || array_sum(array_column($variants, 'weight')) <= 0) {
            throw new InvalidArgumentException('An experiment needs at least two variants with a positive total weight.');
        }

        return Experiment::query()->create([
            'key' => $data['key'],
            'name' => $data['name'],
            'hypothesis' => $data['hypothesis'] ?? null,
            'variants' => $variants,
            'guard_metric' => $data['guard_metric'] ?? 'gross_margin',
            'guard_threshold' => $data['guard_threshold'] ?? null,
            'status' => 'draft',
        ]);
    }

    public function start(Experiment $experiment): Experiment
    {
        $experiment->update(['status' => 'running', 'started_at' => now(), 'stopped_at' => null, 'stopped_reason' => null]);

        return $experiment;
    }

    public function stop(Experiment $experiment, string $reason): Experiment
    {
        $experiment->update(['status' => 'stopped', 'stopped_at' => now(), 'stopped_reason' => mb_substr($reason, 0, 500)]);

        return $experiment;
    }

    /**
     * The workspace's variant of a running experiment, assigning it on first
     * use, or null when the experiment does not exist or is not running.
     */
    public function variant(string $key, string $workspaceId): ?string
    {
        $experiment = Experiment::query()->where('key', $key)->first();

        if ($experiment === null || ! $experiment->isRunning()) {
            return null;
        }

        $existing = ExperimentAssignment::query()->where('experiment_id', $experiment->id)->where('workspace_id', $workspaceId)->value('variant');

        if ($existing !== null) {
            return $existing;
        }

        $variant = $this->pick($experiment, $workspaceId);

        try {
            ExperimentAssignment::query()->create(['experiment_id' => $experiment->id, 'workspace_id' => $workspaceId, 'variant' => $variant, 'assigned_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return ExperimentAssignment::query()->where('experiment_id', $experiment->id)->where('workspace_id', $workspaceId)->value('variant');
        }

        return $variant;
    }

    /**
     * One setting of the workspace's variant, or `$default` outside a
     * running experiment.
     */
    public function setting(string $key, string $workspaceId, string $setting, mixed $default = null): mixed
    {
        $variant = $this->variant($key, $workspaceId);

        if ($variant === null) {
            return $default;
        }

        $experiment = Experiment::query()->where('key', $key)->first();
        $match = collect($experiment?->variants ?? [])->firstWhere('key', $variant);

        return $match['settings'][$setting] ?? $default;
    }

    /**
     * @return list<array{variant: string, workspaces: int, paying: int, conversion_rate: float, revenue_usd: float, provider_cost_usd: float, margin_per_workspace_usd: float}>
     */
    public function results(Experiment $experiment): array
    {
        $rows = DB::table('experiment_assignments')
            ->leftJoin('fact_workspace_daily', function ($join) use ($experiment): void {
                $join->on('fact_workspace_daily.workspace_id', '=', 'experiment_assignments.workspace_id')
                    ->where('fact_workspace_daily.date', '>=', ($experiment->started_at ?? $experiment->created_at)->toDateString());
            })
            ->where('experiment_assignments.experiment_id', $experiment->id)
            ->selectRaw('experiment_assignments.variant as variant,
                count(distinct experiment_assignments.workspace_id) as workspaces,
                count(distinct case when fact_workspace_daily.is_paying then experiment_assignments.workspace_id end) as paying,
                coalesce(sum(fact_workspace_daily.revenue_usd), 0) as revenue,
                coalesce(sum(fact_workspace_daily.provider_cost_usd), 0) as cost')
            ->groupBy('experiment_assignments.variant')
            ->get();

        $fee = (float) config('billing.margin_floor.payment_fee_rate', 0);

        return $rows->map(fn (object $row): array => [
            'variant' => $row->variant,
            'workspaces' => (int) $row->workspaces,
            'paying' => (int) $row->paying,
            'conversion_rate' => (int) $row->workspaces > 0 ? round((int) $row->paying / (int) $row->workspaces, 4) : 0.0,
            'revenue_usd' => round((float) $row->revenue, 2),
            'provider_cost_usd' => round((float) $row->cost, 2),
            'margin_per_workspace_usd' => (int) $row->workspaces > 0 ? round(((float) $row->revenue * (1 - $fee) - (float) $row->cost) / (int) $row->workspaces, 4) : 0.0,
        ])->all();
    }

    /**
     * Stops every running experiment that has a variant below its guard
     * threshold, once that variant has at least 30 workspaces.
     *
     * @return list<string> the keys of the experiments stopped
     */
    public function guard(): array
    {
        $stopped = [];

        foreach (Experiment::query()->where('status', 'running')->whereNotNull('guard_threshold')->get() as $experiment) {
            foreach ($this->results($experiment) as $result) {
                if ($result['workspaces'] >= 30 && $result['margin_per_workspace_usd'] < $experiment->guard_threshold) {
                    $this->stop($experiment, sprintf('Variant "%s" earned $%.2f per workspace, under the $%.2f guard.', $result['variant'], $result['margin_per_workspace_usd'], $experiment->guard_threshold));
                    $stopped[] = $experiment->key;

                    break;
                }
            }
        }

        return $stopped;
    }

    private function pick(Experiment $experiment, string $workspaceId): string
    {
        $variants = $experiment->variants;
        $total = array_sum(array_column($variants, 'weight'));
        $point = hexdec(substr(hash('sha256', $experiment->key.':'.$workspaceId), 0, 8)) % max(1, $total);

        foreach ($variants as $variant) {
            $point -= (int) $variant['weight'];

            if ($point < 0) {
                return (string) $variant['key'];
            }
        }

        return (string) $variants[array_key_last($variants)]['key'];
    }
}
