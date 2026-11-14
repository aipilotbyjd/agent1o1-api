<?php

namespace App\Services\System\Backups;

use App\Enums\System\BackupStatus;
use App\Enums\System\BackupType;
use App\Jobs\System\RunBackupJob;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\System\Backup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Creates database backups and keeps their records. The dump itself is
 * `spatie/laravel-backup`'s `backup:run`; this adds the record the Super
 * Admin panel lists, one run at a time, and a pre-signed download link.
 *
 * There is deliberately no way to delete or overwrite a backup here: the
 * bucket enforces it too (Object Lock, and no delete permission).
 */
class BackupService
{
    private const int ERROR_LIMIT = 2000;

    /**
     * Whether a run is already queued or in progress.
     */
    public function hasActiveRun(): bool
    {
        $this->failStaleRuns();

        return Backup::query()->whereIn('status', [BackupStatus::Pending, BackupStatus::Running])->exists();
    }

    /**
     * Records a pending backup and queues it. Returns null when a run is
     * already active, so two dumps never overlap.
     */
    public function queue(BackupType $type, ?SuperAdmin $actor = null): ?Backup
    {
        if ($this->hasActiveRun()) {
            return null;
        }

        $backup = Backup::query()->create([
            'type' => $type,
            'status' => BackupStatus::Pending,
            'disk' => (string) config('backups.disk'),
            'triggered_by' => $actor?->id,
        ]);

        RunBackupJob::dispatch($backup->id);

        return $backup;
    }

    /**
     * Runs the backup now, on this process, and records the result. Never
     * throws: a failure is stored on the record.
     */
    public function execute(Backup $backup): Backup
    {
        if ($backup->status !== BackupStatus::Pending) {
            return $backup;
        }

        $backup->update(['status' => BackupStatus::Running, 'started_at' => now()]);

        $path = config('backup.backup.name').'/'.$backup->id.'.zip';

        try {
            $this->runDump($backup);

            $disk = Storage::disk($backup->disk);

            if (! $disk->exists($path)) {
                throw new RuntimeException("The backup finished but {$path} is not on the {$backup->disk} disk.");
            }

            $backup->update([
                'status' => BackupStatus::Completed,
                'path' => $path,
                'size_bytes' => $disk->size($path),
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $backup->update([
                'status' => BackupStatus::Failed,
                'error' => mb_substr($exception->getMessage(), 0, self::ERROR_LIMIT),
                'finished_at' => now(),
            ]);
        }

        return $backup;
    }

    /**
     * Runs `backup:run` for the database only, writing `{id}.zip` to the
     * backup's disk.
     *
     * @throws RuntimeException when the command fails
     */
    protected function runDump(Backup $backup): void
    {
        $exitCode = Artisan::call('backup:run', [
            '--only-db' => true,
            '--filename' => $backup->id.'.zip',
            '--only-to-disk' => $backup->disk,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException(trim(Artisan::output()) ?: 'backup:run failed.');
        }
    }

    /**
     * Marks runs whose worker died (still pending or running long after they
     * should have finished) as failed, so they stop blocking new backups.
     */
    public function failStaleRuns(): int
    {
        $stale = Backup::query()
            ->whereIn('status', [BackupStatus::Pending, BackupStatus::Running])
            ->where('created_at', '<', now()->subMinutes((int) config('backups.stale_after_minutes')))
            ->get();

        foreach ($stale as $backup) {
            $backup->update([
                'status' => BackupStatus::Failed,
                'error' => 'The backup did not finish: its worker stopped or timed out.',
                'finished_at' => now(),
            ]);
        }

        return $stale->count();
    }

    /**
     * A short-lived pre-signed link straight to the file in the bucket.
     *
     * @throws RuntimeException when the backup has no file or the disk cannot sign links
     */
    public function downloadUrl(Backup $backup): string
    {
        if (! $backup->isDownloadable()) {
            throw new RuntimeException('This backup has no file to download.');
        }

        $disk = Storage::disk($backup->disk);

        if (! $disk->providesTemporaryUrls()) {
            throw new RuntimeException("The {$backup->disk} disk cannot create download links.");
        }

        return $disk->temporaryUrl(
            $backup->path,
            now()->addMinutes((int) config('backups.download_url_ttl_minutes')),
            ['ResponseContentDisposition' => 'attachment; filename="'.basename($backup->path).'"'],
        );
    }
}
