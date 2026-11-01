<?php

namespace App\Console\Commands\SuperAdmin;

use App\Models\SuperAdmin\AlertEvent;
use Illuminate\Console\Command;

class AlertsPruneCommand extends Command
{
    protected $signature = 'alerts:prune';

    protected $description = 'Deletes resolved alert events past their retention.';

    public function handle(): int
    {
        $deleted = AlertEvent::query()
            ->where('status', AlertEvent::STATUS_RESOLVED)
            ->where('resolved_at', '<', now()->subDays((int) config('super_admin_alerts.engine.keep_resolved_days')))
            ->delete();

        $this->info("Deleted {$deleted} resolved alert event(s).");

        return self::SUCCESS;
    }
}
