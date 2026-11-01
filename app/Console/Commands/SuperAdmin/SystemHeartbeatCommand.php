<?php

namespace App\Console\Commands\SuperAdmin;

use App\Services\SuperAdmin\System\SchedulerMonitor;
use Illuminate\Console\Command;

/**
 * Scheduled every minute. Its only job is to prove the scheduler is alive:
 * System Health reports the scheduler as down when this stops running.
 */
class SystemHeartbeatCommand extends Command
{
    protected $signature = 'system:heartbeat';

    protected $description = 'Records that the scheduler is running.';

    public function handle(SchedulerMonitor $scheduler): int
    {
        $scheduler->beat();

        return self::SUCCESS;
    }
}
