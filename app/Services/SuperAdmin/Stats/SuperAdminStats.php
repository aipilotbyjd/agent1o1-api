<?php

namespace App\Services\SuperAdmin\Stats;

use App\Enums\RunStatus;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Subscription;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The numbers on the Super Admin dashboard: counts and sums read from this
 * database. Revenue is deliberately not here: money lives in Stripe, not in
 * these tables.
 */
class SuperAdminStats
{
    /**
     * The metrics `timeseries()` can draw, each a (model, aggregate) pair.
     *
     * @var list<string>
     */
    public const METRICS = ['signups', 'workspaces', 'runs', 'failed_runs', 'credits'];

    /**
     * @return array<string, mixed>
     */
    public function overview(int $days): array
    {
        return Cache::remember("super-admin-stats:overview:{$days}", 60, function () use ($days): array {
            $since = now()->subDays($days);

            $runsByStatus = Run::query()
                ->where('created_at', '>=', $since)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            $completed = (int) ($runsByStatus[RunStatus::Completed->value] ?? 0);
            $failed = (int) ($runsByStatus[RunStatus::Failed->value] ?? 0);

            return [
                'window_days' => $days,
                'users' => [
                    'total' => User::query()->count(),
                    'new' => User::query()->where('created_at', '>=', $since)->count(),
                    'suspended' => User::query()->whereNotNull('suspended_at')->count(),
                ],
                'workspaces' => [
                    'total' => Workspace::query()->count(),
                    'new' => Workspace::query()->where('created_at', '>=', $since)->count(),
                    'suspended' => Workspace::query()->whereNotNull('suspended_at')->count(),
                    'active' => Run::query()->where('created_at', '>=', $since)->distinct()->count('workspace_id'),
                ],
                'runs' => [
                    'total' => (int) $runsByStatus->sum(),
                    'by_status' => $runsByStatus->map(fn ($total): int => (int) $total)->all(),
                    'success_rate' => $completed + $failed === 0 ? null : round($completed / ($completed + $failed), 4),
                ],
                'credits_consumed' => (int) CreditTransaction::query()->where('created_at', '>=', $since)->sum('credits'),
                'paid_subscriptions' => Subscription::query()->whereIn('stripe_status', ['active', 'trialing'])->count(),
            ];
        });
    }

    /**
     * One value per day, zero-filled, for a metric in `METRICS`.
     *
     * @return list<array{date: string, value: int}>
     */
    public function timeseries(string $metric, Carbon $from, Carbon $to): array
    {
        [$query, $aggregate] = match ($metric) {
            'signups' => [User::query(), 'count(*)'],
            'workspaces' => [Workspace::query(), 'count(*)'],
            'runs' => [Run::query(), 'count(*)'],
            'failed_runs' => [Run::query()->where('status', RunStatus::Failed->value), 'count(*)'],
            'credits' => [CreditTransaction::query(), 'sum(credits)'],
        };

        $totals = $this->perDay($query, $aggregate, $from, $to);

        $series = [];

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $date = $day->toDateString();
            $series[] = ['date' => $date, 'value' => (int) ($totals[$date] ?? 0)];
        }

        return $series;
    }

    /**
     * The workspaces that consumed the most credits in the last `$days`.
     *
     * @return list<array{workspace_id: string, name: ?string, credits: int}>
     */
    public function topWorkspaces(int $days, int $limit = 10): array
    {
        $rows = CreditTransaction::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->select('workspace_id', DB::raw('sum(credits) as credits'))
            ->groupBy('workspace_id')
            ->orderByDesc('credits')
            ->limit($limit)
            ->get();

        $names = Workspace::query()->whereIn('id', $rows->pluck('workspace_id'))->pluck('name', 'id');

        return $rows->map(fn ($row): array => [
            'workspace_id' => $row->workspace_id,
            'name' => $names[$row->workspace_id] ?? null,
            'credits' => (int) $row->credits,
        ])->all();
    }

    /**
     * @param  Builder<*>  $query
     * @return array<string, int|string>
     */
    private function perDay(Builder $query, string $aggregate, Carbon $from, Carbon $to): array
    {
        return $query
            ->where('created_at', '>=', $from->copy()->startOfDay())
            ->where('created_at', '<=', $to->copy()->endOfDay())
            ->select(DB::raw('date(created_at) as day'), DB::raw("{$aggregate} as total"))
            ->groupBy(DB::raw('date(created_at)'))
            ->pluck('total', 'day')
            ->all();
    }
}
