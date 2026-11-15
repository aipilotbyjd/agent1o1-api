<?php

namespace App\Console\Commands\SuperAdmin;

use App\Services\SuperAdmin\Operations\TaskCatalog;
use Illuminate\Console\Command;

/**
 * Adds any built-in checklist task that is missing. Safe to run any time: it
 * never changes a task that already exists.
 */
class OperationsSyncTasksCommand extends Command
{
    protected $signature = 'operations:sync-tasks';

    protected $description = 'Adds the built-in operations checklist tasks that are missing.';

    public function handle(TaskCatalog $catalog): int
    {
        $added = $catalog->sync();

        $this->info($added === 0 ? 'The checklist is up to date.' : "Added {$added} checklist task(s).");

        return self::SUCCESS;
    }
}
