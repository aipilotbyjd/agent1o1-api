<?php

namespace App\Console\Commands;

use App\Services\ExecutionArchiveService;
use Illuminate\Console\Command;

class ArchiveOldExecutionLogsCommand extends Command
{
    protected $signature = 'executions:archive-logs {--days=}';

    protected $description = 'Move execution logs older than the retention window into cold storage.';

    public function handle(ExecutionArchiveService $service): int
    {
        $days = (int) ($this->option('days') ?: config('workflow.log_archive_days', 14));

        $count = $service->archiveOlderThan($days);

        $this->info("Archived {$count} execution log(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
