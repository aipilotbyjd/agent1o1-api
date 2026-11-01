<?php

namespace App\Console\Commands\SuperAdmin;

use App\Services\SuperAdmin\System\HealthSnapshotter;
use App\Services\SuperAdmin\System\SchedulerMonitor;
use Illuminate\Console\Command;

/**
 * Scheduled every five minutes: stores the result of every health check for
 * the uptime history, and clears out snapshots and scheduler records past
 * their retention.
 */
class SystemHealthSnapshotCommand extends Command
{
    protected $signature = 'system:health-snapshot';

    protected $description = 'Stores a snapshot of the system health checks and prunes old history.';

    public function handle(HealthSnapshotter $snapshots, SchedulerMonitor $scheduler): int
    {
        $snapshot = $snapshots->capture();

        $pruned = $snapshots->prune() + $scheduler->prune();

        $this->info("System is {$snapshot->status}. Pruned {$pruned} old record(s).");

        return self::SUCCESS;
    }
}
