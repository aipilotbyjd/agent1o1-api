<?php

namespace App\Console\Commands\SuperAdmin;

use App\Enums\System\BackupType;
use App\Services\System\Backups\BackupService;
use Illuminate\Console\Command;

/**
 * Scheduled (`routes/console.php`): queues a database backup. Skips quietly
 * when one is already running.
 */
class SystemBackupCommand extends Command
{
    protected $signature = 'system:backup';

    protected $description = 'Queues a database backup to the immutable backups disk.';

    public function handle(BackupService $backups): int
    {
        if (! config('backups.enabled')) {
            $this->warn('Backups are disabled (BACKUPS_ENABLED).');

            return self::SUCCESS;
        }

        $backup = $backups->queue(BackupType::Scheduled);

        $this->info($backup === null ? 'A backup is already running; skipped.' : "Queued backup {$backup->id}.");

        return self::SUCCESS;
    }
}
