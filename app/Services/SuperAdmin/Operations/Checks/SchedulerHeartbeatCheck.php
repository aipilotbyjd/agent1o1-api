<?php

namespace App\Services\SuperAdmin\Operations\Checks;

use App\Services\SuperAdmin\Operations\CheckResult;
use App\Services\SuperAdmin\Operations\OperationsCheck;
use App\Services\SuperAdmin\System\SchedulerMonitor;

class SchedulerHeartbeatCheck implements OperationsCheck
{
    public function __construct(private readonly SchedulerMonitor $scheduler) {}

    public function key(): string
    {
        return 'scheduler_heartbeat';
    }

    public function evaluate(): CheckResult
    {
        $beat = $this->scheduler->heartbeatAt();

        if ($beat === null) {
            return CheckResult::fail('The scheduler has never reported in. Make sure it runs every minute.');
        }

        $seconds = (int) $beat->diffInSeconds(now(), true);

        return match (true) {
            $seconds <= (int) config('operations.checks.scheduler_warn_seconds') => CheckResult::ok("The scheduler reported {$seconds} seconds ago."),
            $seconds <= (int) config('operations.checks.scheduler_fail_seconds') => CheckResult::warn('The scheduler reported '.$beat->diffForHumans().'. It may be running late.'),
            default => CheckResult::fail('No scheduler heartbeat since '.$beat->diffForHumans().'. The scheduler may have stopped.'),
        };
    }
}
