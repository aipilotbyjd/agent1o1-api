<?php

namespace App\Jobs\System;

use App\Enums\Queue;
use App\Models\System\Backup;
use App\Services\System\Backups\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs one queued database backup (a "Back up now" press or a scheduled
 * run) and records the result on its `Backup` row. Never retried: a second
 * attempt would race the first one's dump.
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public string $backupId)
    {
        $this->onConnection(config('queue.long_running_connection'));
        $this->onQueue(Queue::LongRunning->value);
    }

    public function handle(BackupService $backups): void
    {
        $backup = Backup::query()->find($this->backupId);

        if ($backup !== null) {
            $backups->execute($backup);
        }
    }
}
