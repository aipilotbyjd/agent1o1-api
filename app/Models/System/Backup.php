<?php

namespace App\Models\System;

use App\Enums\System\BackupStatus;
use App\Enums\System\BackupType;
use App\Models\SuperAdmin\SuperAdmin;
use Database\Factories\System\BackupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One database backup run and the file it produced. The record is the app's
 * view of a file in the immutable backups bucket: it can be created and
 * moved forward (pending, running, then completed or failed), but never
 * deleted, and a finished one never changes.
 */
#[Fillable(['type', 'status', 'disk', 'path', 'size_bytes', 'triggered_by', 'error', 'started_at', 'finished_at'])]
class Backup extends Model
{
    /** @use HasFactory<BackupFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Backups are immutable and cannot be deleted.');
        });

        static::updating(function (Backup $backup): void {
            $was = BackupStatus::tryFrom((string) $backup->getRawOriginal('status'));

            if ($was?->isFinal()) {
                throw new LogicException('A finished backup cannot be changed.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => BackupType::class,
            'status' => BackupStatus::class,
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SuperAdmin, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'triggered_by');
    }

    public function isDownloadable(): bool
    {
        return $this->status === BackupStatus::Completed && filled($this->path);
    }
}
