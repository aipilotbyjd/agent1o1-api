<?php

namespace App\Console\Commands\Admin;

use App\Services\Ai\Operations\RollUpModelUsage;
use Illuminate\Console\Command;

/**
 * Rebuilds the per-day model usage table for the latest days. Scheduled
 * hourly; run with a larger `--days` to backfill or to re-cost after a price
 * change. See `RollUpModelUsage`.
 */
class ModelUsageRollupCommand extends Command
{
    protected $signature = 'model-usage:rollup {--days= : How many of the latest days to rebuild (default: config model_operations.rollup_days, at most 31)}';

    protected $description = 'Rebuilds the daily model usage table from node run and chat message usage.';

    public function handle(RollUpModelUsage $rollup): int
    {
        $result = $rollup->run($this->option('days') === null ? null : (int) $this->option('days'));

        $this->info("Rebuilt {$result['days']} day(s): {$result['rows']} row(s).");

        return self::SUCCESS;
    }
}
