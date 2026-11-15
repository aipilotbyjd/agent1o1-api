<?php

namespace App\Services\SuperAdmin\Operations\Checks;

use App\Enums\System\BackupStatus;
use App\Models\System\Backup;
use App\Services\SuperAdmin\Operations\CheckResult;
use App\Services\SuperAdmin\Operations\OperationsCheck;

class BackupsCheck implements OperationsCheck
{
    public function key(): string
    {
        return 'backups';
    }

    public function evaluate(): CheckResult
    {
        if (! config('backups.enabled')) {
            return CheckResult::warn('Backups are switched off on this server.');
        }

        $completed = Backup::query()->where('status', BackupStatus::Completed)->latest('finished_at')->first();
        $failed = Backup::query()->where('status', BackupStatus::Failed)->latest('finished_at')->first();

        if ($failed !== null && ($completed === null || $failed->finished_at?->gt($completed->finished_at))) {
            return CheckResult::fail('The last backup failed'.($failed->error ? ': '.$failed->error : '.'));
        }

        if ($completed === null) {
            return CheckResult::fail('No backup has completed yet.');
        }

        $hours = (int) config('operations.checks.backups_max_age_hours');

        return $completed->finished_at->gt(now()->subHours($hours))
            ? CheckResult::ok('The last backup finished '.$completed->finished_at->diffForHumans().'.')
            : CheckResult::fail("No backup has finished in the last {$hours} hours.");
    }
}
