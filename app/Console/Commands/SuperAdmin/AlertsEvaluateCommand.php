<?php

namespace App\Console\Commands\SuperAdmin;

use App\Services\SuperAdmin\Alerts\AlertEngine;
use Illuminate\Console\Command;

/**
 * Scheduled every minute: checks every enabled alert rule, opens, updates and
 * resolves alert events, and sends the notifications. See `AlertEngine`.
 */
class AlertsEvaluateCommand extends Command
{
    protected $signature = 'alerts:evaluate';

    protected $description = 'Evaluates the Super Admin alert rules and sends any notifications due.';

    public function handle(AlertEngine $engine): int
    {
        $summary = $engine->run();

        $this->info(sprintf(
            '%d rule(s) checked: %d new, %d resolved, %d notification(s) sent, %d failed.',
            $summary['rules'], $summary['fired'], $summary['resolved'], $summary['notified'], $summary['failed'],
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
