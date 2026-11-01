<?php

namespace App\Services\SuperAdmin\System\Checks;

use App\Services\SuperAdmin\System\FailedJobService;
use App\Services\SuperAdmin\System\QueueInspector;

/**
 * Queues that have jobs waiting too long, and the size of the failed-job pile.
 */
class QueueCheck implements HealthCheck
{
    public function __construct(
        private readonly QueueInspector $queues,
        private readonly FailedJobService $failedJobs,
    ) {}

    public function name(): string
    {
        return 'queues';
    }

    public function run(): array
    {
        $queues = $this->queues->queues();
        $failed = $this->failedJobs->count();

        $slow = array_values(array_filter($queues, fn (array $queue): bool => $queue['status'] !== self::HEALTHY));
        $failedStatus = $failed >= config('super_admin_health.queues.failed_jobs_critical')
            ? self::DOWN
            : ($failed >= config('super_admin_health.queues.failed_jobs_warning') ? self::DEGRADED : self::HEALTHY);

        $status = collect([...array_column($slow, 'status'), $failedStatus])
            ->reduce(fn (string $worst, string $status): string => $this->worse($worst, $status), self::HEALTHY);

        $message = match (true) {
            $slow !== [] => count($slow).' queue(s) have jobs waiting too long: '.implode(', ', array_column($slow, 'name')).'.',
            $failedStatus !== self::HEALTHY => "{$failed} failed jobs are waiting for attention.",
            default => 'Queues are moving.',
        };

        return [
            'status' => $status,
            'message' => $message,
            'details' => ['driver' => $this->queues->driver(), 'failed_jobs' => $failed, 'slow_queues' => array_column($slow, 'name')],
        ];
    }

    private function worse(string $a, string $b): string
    {
        $rank = [self::HEALTHY => 0, self::DEGRADED => 1, self::DOWN => 2];

        return ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
    }
}
