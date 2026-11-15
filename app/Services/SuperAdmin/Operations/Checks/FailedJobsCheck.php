<?php

namespace App\Services\SuperAdmin\Operations\Checks;

use App\Services\SuperAdmin\Operations\CheckResult;
use App\Services\SuperAdmin\Operations\OperationsCheck;
use App\Services\SuperAdmin\System\FailedJobService;

class FailedJobsCheck implements OperationsCheck
{
    public function __construct(private readonly FailedJobService $failedJobs) {}

    public function key(): string
    {
        return 'failed_jobs';
    }

    public function evaluate(): CheckResult
    {
        $count = $this->failedJobs->count();

        return match (true) {
            $count === 0 => CheckResult::ok('No failed jobs.'),
            $count >= (int) config('operations.checks.failed_jobs_fail_at') => CheckResult::fail("{$count} failed jobs are waiting."),
            default => CheckResult::warn("{$count} failed ".($count === 1 ? 'job is' : 'jobs are').' waiting.'),
        };
    }
}
