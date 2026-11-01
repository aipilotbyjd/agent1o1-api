<?php

namespace App\Console\Commands\Admin;

use App\Services\Ai\Operations\SpendGuard;
use Illuminate\Console\Command;

/**
 * Checks model spend against the budget and each workspace's usual spend, and
 * raises an alert for anything new today. Scheduled hourly, after the usage
 * rollup it reads. See `SpendGuard`.
 */
class ModelSpendCheckCommand extends Command
{
    protected $signature = 'model-spend:check';

    protected $description = 'Alerts when model spend passes the daily budget or a workspace spikes.';

    public function handle(SpendGuard $guard): int
    {
        $findings = $guard->findings();

        foreach ($findings as $finding) {
            $this->warn($finding['message']);
        }

        $raised = $guard->alert();

        $this->info($findings === [] ? 'Model spend looks normal.' : "{$raised} new alert(s) raised.");

        return self::SUCCESS;
    }
}
