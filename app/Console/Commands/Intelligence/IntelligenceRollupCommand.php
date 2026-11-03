<?php

namespace App\Console\Commands\Intelligence;

use App\Services\Intelligence\FactRollup;
use Illuminate\Console\Command;

/**
 * Rebuilds the daily fact tables the admin intelligence reads.
 */
class IntelligenceRollupCommand extends Command
{
    protected $signature = 'intelligence:rollup {--days= : How many of the latest days to rebuild (default intelligence.rollup_days)}';

    protected $description = 'Rebuilds the daily usage and workspace fact tables from the ledgers.';

    public function handle(FactRollup $rollup): int
    {
        $result = $rollup->run((int) ($this->option('days') ?? config('intelligence.rollup_days', 2)));

        $this->info("Rebuilt {$result['days']} day(s): {$result['usage_rows']} usage row(s), {$result['workspace_rows']} workspace row(s).");

        return self::SUCCESS;
    }
}
