<?php

namespace App\Services\SuperAdmin\System\Checks;

use App\Services\SuperAdmin\System\SchedulerMonitor;

class SchedulerCheck implements HealthCheck
{
    public function __construct(private readonly SchedulerMonitor $scheduler) {}

    public function name(): string
    {
        return 'scheduler';
    }

    public function run(): array
    {
        $beat = $this->scheduler->heartbeatAt();

        if ($beat === null) {
            return ['status' => self::DEGRADED, 'message' => 'The scheduler has not reported a heartbeat yet.'];
        }

        $age = (int) $beat->diffInSeconds(now(), true);

        if ($age > config('super_admin_health.scheduler.heartbeat_stale_seconds')) {
            return [
                'status' => self::DOWN,
                'message' => "The scheduler has been silent for {$age} seconds.",
                'details' => ['last_heartbeat_at' => $beat->toIso8601String(), 'age_seconds' => $age],
            ];
        }

        $overdue = array_values(array_filter($this->scheduler->commands(), fn (array $command): bool => $command['overdue']));

        if ($overdue !== []) {
            return [
                'status' => self::DEGRADED,
                'message' => count($overdue).' scheduled command(s) are overdue.',
                'details' => ['overdue' => array_column($overdue, 'command'), 'age_seconds' => $age],
            ];
        }

        return ['status' => self::HEALTHY, 'message' => 'The scheduler is running.', 'details' => ['age_seconds' => $age]];
    }
}
