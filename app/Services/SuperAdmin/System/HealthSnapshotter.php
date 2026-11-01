<?php

namespace App\Services\SuperAdmin\System;

use App\Models\SuperAdmin\SystemHealthSnapshot;
use App\Services\SuperAdmin\System\Checks\HealthCheck;
use Illuminate\Support\Carbon;

/**
 * Stores the health result every few minutes and answers questions about the
 * past: uptime over 24 hours, 7 days and 30 days, and the incidents in it.
 */
class HealthSnapshotter
{
    public function __construct(private readonly HealthChecker $checker) {}

    public function capture(): SystemHealthSnapshot
    {
        $result = $this->checker->run();

        return SystemHealthSnapshot::query()->create([
            'status' => $result['status'],
            'checks' => array_map(fn (array $check): array => ['status' => $check['status'], 'message' => $check['message']], $result['checks']),
            'created_at' => now(),
        ]);
    }

    public function prune(): int
    {
        return SystemHealthSnapshot::query()
            ->where('created_at', '<', now()->subDays((int) config('super_admin_health.snapshots.keep_days')))
            ->delete();
    }

    /**
     * @return array<string, array{snapshots: int, healthy_percent: ?float, availability_percent: ?float}>
     */
    public function uptime(): array
    {
        $windows = ['24h' => now()->subDay(), '7d' => now()->subDays(7), '30d' => now()->subDays(30)];
        $report = [];

        foreach ($windows as $label => $since) {
            $counts = SystemHealthSnapshot::query()
                ->where('created_at', '>=', $since)
                ->selectRaw('count(*) as total, sum(case when status = ? then 1 else 0 end) as healthy, sum(case when status != ? then 1 else 0 end) as up', [HealthCheck::HEALTHY, HealthCheck::DOWN])
                ->first();

            $total = (int) $counts->total;

            $report[$label] = [
                'snapshots' => $total,
                'healthy_percent' => $total === 0 ? null : round((int) $counts->healthy / $total * 100, 2),
                'availability_percent' => $total === 0 ? null : round((int) $counts->up / $total * 100, 2),
            ];
        }

        return $report;
    }

    /**
     * Periods in the last `$days` where the status was not healthy.
     *
     * @return list<array{started_at: string, ended_at: ?string, worst_status: string, checks: list<string>}>
     */
    public function incidents(int $days): array
    {
        $snapshots = SystemHealthSnapshot::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get();

        $incidents = [];
        $current = null;

        foreach ($snapshots as $snapshot) {
            if ($snapshot->status === HealthCheck::HEALTHY) {
                if ($current !== null) {
                    $current['ended_at'] = $snapshot->created_at->toIso8601String();
                    $incidents[] = $current;
                    $current = null;
                }

                continue;
            }

            $problems = array_keys(array_filter($snapshot->checks, fn (array $check): bool => in_array($check['status'], [HealthCheck::DEGRADED, HealthCheck::DOWN], true)));

            $current ??= ['started_at' => $snapshot->created_at->toIso8601String(), 'ended_at' => null, 'worst_status' => $snapshot->status, 'checks' => []];
            $current['checks'] = array_values(array_unique([...$current['checks'], ...$problems]));

            if ($snapshot->status === HealthCheck::DOWN) {
                $current['worst_status'] = HealthCheck::DOWN;
            }
        }

        if ($current !== null) {
            $incidents[] = $current;
        }

        return array_reverse($incidents);
    }

    /**
     * @return list<array{at: string, status: string}>
     */
    public function timeline(int $hours, int $limit = 500): array
    {
        return SystemHealthSnapshot::query()
            ->where('created_at', '>=', Carbon::now()->subHours($hours))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (SystemHealthSnapshot $snapshot): array => ['at' => $snapshot->created_at->toIso8601String(), 'status' => $snapshot->status])
            ->all();
    }
}
