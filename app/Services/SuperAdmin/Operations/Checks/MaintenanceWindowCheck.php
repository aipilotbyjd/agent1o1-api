<?php

namespace App\Services\SuperAdmin\Operations\Checks;

use App\Models\Broadcasts\Announcement;
use App\Services\SuperAdmin\Operations\CheckResult;
use App\Services\SuperAdmin\Operations\OperationsCheck;

class MaintenanceWindowCheck implements OperationsCheck
{
    public function key(): string
    {
        return 'maintenance_window';
    }

    public function evaluate(): CheckResult
    {
        $window = Announcement::openBlockingWindow();

        if ($window === null) {
            return CheckResult::ok('The app is open for changes.');
        }

        return CheckResult::warn('The app is read-only for members'.($window->ends_at ? ' until '.$window->ends_at->utc()->format('M j, H:i').' UTC.' : '.'));
    }
}
