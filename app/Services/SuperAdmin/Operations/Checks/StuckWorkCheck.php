<?php

namespace App\Services\SuperAdmin\Operations\Checks;

use App\Services\SuperAdmin\Operations\CheckResult;
use App\Services\SuperAdmin\Operations\OperationsCheck;
use App\Services\SuperAdmin\System\StuckWorkDetector;

class StuckWorkCheck implements OperationsCheck
{
    public function __construct(private readonly StuckWorkDetector $stuck) {}

    public function key(): string
    {
        return 'stuck_work';
    }

    public function evaluate(): CheckResult
    {
        $total = $this->stuck->report()['total'];

        return match (true) {
            $total === 0 => CheckResult::ok('Nothing is stuck.'),
            $total >= (int) config('operations.checks.stuck_work_fail_at') => CheckResult::fail("{$total} items look stuck."),
            default => CheckResult::warn("{$total} ".($total === 1 ? 'item looks' : 'items look').' stuck.'),
        };
    }
}
