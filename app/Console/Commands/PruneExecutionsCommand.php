<?php

namespace App\Console\Commands;

use App\Services\ExecutionArchiveService;
use Illuminate\Console\Command;

class PruneExecutionsCommand extends Command
{
    protected $signature = 'executions:prune {--days=}';

    protected $description = 'Delete terminal executions older than the retention window.';

    public function handle(ExecutionArchiveService $service): int
    {
        $days = (int) ($this->option('days') ?: config('workflow.execution_retention_days', 90));

        $count = $service->pruneOlderThan($days);

        $this->info("Pruned {$count} execution(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
