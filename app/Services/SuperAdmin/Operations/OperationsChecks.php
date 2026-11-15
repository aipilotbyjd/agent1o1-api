<?php

namespace App\Services\SuperAdmin\Operations;

use App\Services\SuperAdmin\Operations\Checks\AuditChainCheck;
use App\Services\SuperAdmin\Operations\Checks\BackupsCheck;
use App\Services\SuperAdmin\Operations\Checks\FailedJobsCheck;
use App\Services\SuperAdmin\Operations\Checks\MaintenanceWindowCheck;
use App\Services\SuperAdmin\Operations\Checks\OpenAlertsCheck;
use App\Services\SuperAdmin\Operations\Checks\SchedulerHeartbeatCheck;
use App\Services\SuperAdmin\Operations\Checks\StripeWebhooksCheck;
use App\Services\SuperAdmin\Operations\Checks\StuckWorkCheck;
use App\Services\SuperAdmin\Operations\Checks\SystemHealthCheck;
use Throwable;

/**
 * Every automatic check, looked up by the key a task stores. A check that
 * breaks never takes the checklist down with it: it reports "could not check"
 * and the task asks for a look.
 */
class OperationsChecks
{
    /** @var list<class-string<OperationsCheck>> */
    private const CHECKS = [
        SchedulerHeartbeatCheck::class,
        SystemHealthCheck::class,
        BackupsCheck::class,
        FailedJobsCheck::class,
        StuckWorkCheck::class,
        OpenAlertsCheck::class,
        StripeWebhooksCheck::class,
        AuditChainCheck::class,
        MaintenanceWindowCheck::class,
    ];

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(fn (string $class): string => app($class)->key(), self::CHECKS);
    }

    public function run(?string $key): CheckResult
    {
        foreach (self::CHECKS as $class) {
            $check = app($class);

            if ($check->key() !== $key) {
                continue;
            }

            try {
                return $check->evaluate();
            } catch (Throwable $e) {
                report($e);

                return CheckResult::warn('This check could not run. Look at it by hand.');
            }
        }

        return CheckResult::warn('This task points to a check that no longer exists.');
    }
}
