<?php

namespace App\Services\SuperAdmin\Operations\Checks;

use App\Models\SuperAdmin\SystemHealthSnapshot;
use App\Services\SuperAdmin\Operations\CheckResult;
use App\Services\SuperAdmin\Operations\OperationsCheck;

/**
 * Reads the latest health snapshot instead of probing every service itself, so
 * opening the checklist stays fast.
 */
class SystemHealthCheck implements OperationsCheck
{
    public function key(): string
    {
        return 'system_health';
    }

    public function evaluate(): CheckResult
    {
        $snapshot = SystemHealthSnapshot::query()->latest('created_at')->first();

        if ($snapshot === null) {
            return CheckResult::warn('No health snapshot has been taken yet.');
        }

        if ($snapshot->created_at->lt(now()->subMinutes((int) config('operations.checks.health_max_age_minutes')))) {
            return CheckResult::warn('The last health snapshot is from '.$snapshot->created_at->diffForHumans().'.');
        }

        return match ($snapshot->status) {
            'healthy' => CheckResult::ok('Every service is healthy.'),
            'degraded' => CheckResult::warn('Some services are degraded.'),
            default => CheckResult::fail('At least one service is down.'),
        };
    }
}
