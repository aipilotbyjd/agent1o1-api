<?php

namespace App\Services\Ai\Operations;

use App\Models\Ai\ModelUsageDaily;
use App\Models\Workspaces\Workspace;
use App\Services\SuperAdmin\Alerts\SuperAdminAlerter;
use Illuminate\Support\Facades\Cache;

/**
 * Watches what the platform pays model providers, from the daily rollup, and
 * says so when it looks wrong: the day's spend nearing or passing a budget, or
 * a workspace costing far more than usual. It only tells you. A spike is as
 * likely to be a good customer as an abuser, so what to do about it (suspend,
 * cap, nothing) stays your decision. Thresholds are in
 * `config/model_operations.php`; with none set, it says nothing.
 *
 * The figures are provider cost estimated from the model prices: calls on a
 * workspace's own key cost nothing and calls to an unpriced model cannot be
 * counted. It is as fresh as the last rollup.
 */
class SpendGuard
{
    public function __construct(private readonly SuperAdminAlerter $alerter) {}

    /**
     * What looks wrong right now.
     *
     * @return list<array{code: string, severity: string, message: string, workspace_id: ?string, alert: string}>
     */
    public function findings(): array
    {
        return [...$this->budgetFindings(), ...$this->workspaceFindings()];
    }

    /**
     * Raises an alert for each finding that has not already been raised today,
     * so an hourly check does not repeat itself. Returns how many were raised.
     */
    public function alert(): int
    {
        $raised = 0;

        foreach ($this->findings() as $finding) {
            $key = 'model-spend-alert:'.today()->toDateString().':'.$finding['code'].':'.($finding['workspace_id'] ?? 'platform');

            if (! Cache::add($key, true, now()->endOfDay())) {
                continue;
            }

            $this->alerter->raise($finding['alert'], $finding['message'], ['workspace_id' => $finding['workspace_id']]);
            $raised++;
        }

        return $raised;
    }

    /**
     * @return list<array{code: string, severity: string, message: string, workspace_id: ?string, alert: string}>
     */
    private function budgetFindings(): array
    {
        $budget = config('model_operations.budget.daily_usd');

        if (! is_numeric($budget) || $budget <= 0) {
            return [];
        }

        $spent = (float) ModelUsageDaily::query()->where('date', today()->toDateString())->sum('estimated_cost_usd');
        $warnAt = $budget * (float) config('model_operations.budget.warn_ratio');

        if ($spent >= $budget) {
            return [$this->finding('spend_over_budget', 'spend_budget', sprintf('Model spend today is $%.2f, over the $%.2f daily budget.', $spent, $budget))];
        }

        if ($spent >= $warnAt) {
            return [$this->finding('spend_near_budget', 'spend_budget', sprintf('Model spend today is $%.2f, %d%% of the $%.2f daily budget.', $spent, round($spent / $budget * 100), $budget))];
        }

        return [];
    }

    /**
     * @return list<array{code: string, severity: string, message: string, workspace_id: ?string, alert: string}>
     */
    private function workspaceFindings(): array
    {
        $limit = config('model_operations.workspace.daily_usd');
        $limit = is_numeric($limit) && $limit > 0 ? (float) $limit : null;
        $factor = (float) config('model_operations.workspace.spike_factor');
        $minimum = (float) config('model_operations.workspace.spike_min_usd');

        $today = ModelUsageDaily::query()
            ->where('date', today()->toDateString())
            ->selectRaw('workspace_id, sum(estimated_cost_usd) as cost')
            ->groupBy('workspace_id')
            ->get()
            ->filter(fn ($row): bool => (float) $row->cost > 0);

        if ($today->isEmpty()) {
            return [];
        }

        $previous = ModelUsageDaily::query()
            ->whereBetween('date', [today()->subDays(7)->toDateString(), today()->subDay()->toDateString()])
            ->whereIn('workspace_id', $today->pluck('workspace_id'))
            ->selectRaw('workspace_id, sum(estimated_cost_usd) as cost')
            ->groupBy('workspace_id')
            ->pluck('cost', 'workspace_id');

        $names = Workspace::query()->whereIn('id', $today->pluck('workspace_id'))->pluck('name', 'id');
        $findings = [];

        foreach ($today as $row) {
            $cost = (float) $row->cost;
            $average = (float) ($previous[$row->workspace_id] ?? 0) / 7;
            $name = $names[$row->workspace_id] ?? $row->workspace_id;

            if ($limit !== null && $cost >= $limit) {
                $findings[] = $this->finding('workspace_over_daily_limit', 'spend_spike', sprintf('Workspace "%s" has cost $%.2f today, over the $%.2f a workspace should cost in a day.', $name, $cost, $limit), $row->workspace_id);
            } elseif ($cost >= $minimum && $cost >= $factor * $average) {
                $findings[] = $this->finding('workspace_spend_spike', 'spend_spike', $average > 0
                    ? sprintf('Workspace "%s" has cost $%.2f today, %.1f times its daily average of $%.2f this past week.', $name, $cost, $cost / $average, $average)
                    : sprintf('Workspace "%s" has cost $%.2f today and had no model spend this past week.', $name, $cost), $row->workspace_id);
            }
        }

        return $findings;
    }

    /**
     * @return array{code: string, severity: string, message: string, workspace_id: ?string, alert: string}
     */
    private function finding(string $code, string $alert, string $message, ?string $workspaceId = null): array
    {
        return ['code' => $code, 'severity' => 'warning', 'message' => $message, 'workspace_id' => $workspaceId, 'alert' => $alert];
    }
}
