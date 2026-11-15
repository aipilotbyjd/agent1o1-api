<?php

namespace App\Services\Ai\Operations;

use App\Models\Ai\AiCallEvent;
use App\Models\Ai\ModelUsageDaily;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CostGuard;
use App\Services\Billing\SpendControls;
use App\Services\SuperAdmin\Alerts\SuperAdminAlerter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        return [...$this->budgetFindings(), ...$this->workspaceFindings(), ...$this->hourlyFindings(), ...$this->marginFindings()];
    }

    /**
     * Raises an alert for each finding that has not already been raised today,
     * so an hourly check does not repeat itself. Returns how many were raised.
     */
    public function alert(): int
    {
        $raised = 0;

        foreach ($this->findings() as $finding) {
            $hourly = $finding['code'] === 'workspace_over_hourly_limit';
            $key = 'model-spend-alert:'.($hourly ? now()->format('Y-m-d-H') : today()->toDateString()).':'.$finding['code'].':'.($finding['workspace_id'] ?? 'platform');

            if (! Cache::add($key, true, $hourly ? now()->endOfHour() : now()->endOfDay())) {
                continue;
            }

            $message = $this->throttleIfUnpaid($finding) ? $finding['message'].' It was on free or trial credits, so it is paused until the end of the day.' : $finding['message'];

            $this->alerter->raise($finding['alert'], $message, ['workspace_id' => $finding['workspace_id']]);
            $raised++;
        }

        return $raised;
    }

    /**
     * A free or trial workspace whose spend spikes is paused until the end of
     * the day (`model_operations.workspace.auto_throttle`): it pays nothing,
     * so it is the cheapest thing to stop and the likeliest abuse. A paying
     * workspace is only reported; what to do about it stays the operator's
     * decision.
     *
     * @param  array{code: string, severity: string, message: string, workspace_id: ?string, alert: string}  $finding
     */
    private function throttleIfUnpaid(array $finding): bool
    {
        if ($finding['workspace_id'] === null || ! config('model_operations.workspace.auto_throttle', true)) {
            return false;
        }

        $workspace = Workspace::query()->find($finding['workspace_id']);

        if ($workspace === null || ! app(CostGuard::class)->onFreeOrTrialAllowance($workspace)) {
            return false;
        }

        app(SpendControls::class)->throttle($workspace->id, now()->endOfDay(), $finding['message']);

        return true;
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
     * A workspace whose provider cost in the last hour passed the hourly limit
     * (`model_operations.workspace.hourly_usd`), from the call ledger so it
     * shows within minutes instead of at the next rollup.
     *
     * @return list<array{code: string, severity: string, message: string, workspace_id: ?string, alert: string}>
     */
    private function hourlyFindings(): array
    {
        $limit = config('model_operations.workspace.hourly_usd');

        if (! is_numeric($limit) || $limit <= 0) {
            return [];
        }

        $rows = AiCallEvent::query()
            ->where('occurred_at', '>=', now()->subHour())
            ->where('is_byok', false)
            ->whereNotNull('workspace_id')
            ->selectRaw('workspace_id, sum(provider_cost_usd) as cost')
            ->groupBy('workspace_id')
            ->havingRaw('sum(provider_cost_usd) >= '.number_format((float) $limit, 6, '.', ''))
            ->toBase()
            ->get();

        $names = Workspace::query()->whereIn('id', $rows->pluck('workspace_id'))->pluck('name', 'id');

        return $rows->map(fn (object $row): array => $this->finding('workspace_over_hourly_limit', 'spend_spike', sprintf('Workspace "%s" has cost $%.2f in the last hour, over the $%.2f a workspace should cost in an hour.', $names[$row->workspace_id] ?? $row->workspace_id, (float) $row->cost, (float) $limit), $row->workspace_id))->all();
    }

    /**
     * A paying workspace whose provider cost this month is heading above what
     * its plan brings in: cost has reached `margin_cost_ratio` of what it paid
     * after the card fee, once it is also past `margin_min_usd`. Read from the
     * daily facts, so it is as fresh as their last rebuild.
     *
     * @return list<array{code: string, severity: string, message: string, workspace_id: ?string, alert: string}>
     */
    private function marginFindings(): array
    {
        $ratio = config('model_operations.workspace.margin_cost_ratio');

        if (! is_numeric($ratio) || $ratio <= 0) {
            return [];
        }

        $minimum = (float) config('model_operations.workspace.margin_min_usd');
        $feeRate = (float) config('billing.margin_floor.payment_fee_rate', 0);

        $rows = DB::table('fact_workspace_daily')
            ->whereBetween('date', [today()->startOfMonth()->toDateString(), today()->toDateString()])
            ->selectRaw('workspace_id, sum(revenue_usd) as revenue, sum(provider_cost_usd) as cost')
            ->groupBy('workspace_id')
            ->havingRaw('max(case when is_paying then 1 else 0 end) = 1')
            ->havingRaw('sum(provider_cost_usd) >= '.number_format($minimum, 6, '.', ''))
            ->get()
            ->filter(fn (object $row): bool => (float) $row->cost >= (float) $ratio * (float) $row->revenue * (1 - $feeRate));

        $names = Workspace::query()->whereIn('id', $rows->pluck('workspace_id'))->pluck('name', 'id');

        return $rows->map(function (object $row) use ($names, $feeRate): array {
            $net = (float) $row->revenue * (1 - $feeRate);

            return $this->finding('workspace_over_margin', 'spend_spike', sprintf('Workspace "%s" has cost $%.2f this month against $%.2f it has paid after fees (%s of it).', $names[$row->workspace_id] ?? $row->workspace_id, (float) $row->cost, $net, $net > 0 ? round((float) $row->cost / $net * 100).'%' : 'all'), $row->workspace_id);
        })->values()->all();
    }

    /**
     * @return array{code: string, severity: string, message: string, workspace_id: ?string, alert: string}
     */
    private function finding(string $code, string $alert, string $message, ?string $workspaceId = null): array
    {
        return ['code' => $code, 'severity' => 'warning', 'message' => $message, 'workspace_id' => $workspaceId, 'alert' => $alert];
    }
}
