<?php

namespace App\Services\SuperAdmin\Operations\Checks;

use App\Models\SuperAdmin\ScheduledCommandRun;
use App\Services\SuperAdmin\Operations\CheckResult;
use App\Services\SuperAdmin\Operations\OperationsCheck;

/**
 * The audit-log integrity check runs on a schedule; this reads how its last
 * run went instead of walking the whole log again.
 */
class AuditChainCheck implements OperationsCheck
{
    private const COMMAND = 'super-admin:audit-verify';

    public function key(): string
    {
        return 'audit_chain';
    }

    public function evaluate(): CheckResult
    {
        $run = ScheduledCommandRun::query()->where('command', self::COMMAND)->latest('started_at')->first();

        if ($run === null) {
            return CheckResult::warn('The audit-log integrity check has not run yet.');
        }

        if ($run->finished_at !== null && ($run->exit_code ?? 0) !== 0) {
            return CheckResult::fail('The last audit-log integrity check found a problem'.($run->error ? ': '.$run->error : '.'));
        }

        return $run->started_at->lt(now()->subHours(26))
            ? CheckResult::warn('The audit-log integrity check last ran '.$run->started_at->diffForHumans().'.')
            : CheckResult::ok('The audit log is intact as of '.$run->started_at->diffForHumans().'.');
    }
}
