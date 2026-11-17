<?php

namespace App\Models\Account;

use App\Enums\Account\DataExportStatus;
use App\Models\User;
use Database\Factories\Account\DataExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One request by a user for a copy of their personal data, and the ZIP
 * archive it produced. The file lives on `disk` at `path` until
 * `expires_at`, after which `account:prune-data-exports` removes it.
 */
#[Fillable(['user_id', 'status', 'disk', 'path', 'size_bytes', 'error', 'started_at', 'completed_at', 'expires_at'])]
class DataExport extends Model
{
    /** @use HasFactory<DataExportFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::deleting(function (DataExport $export): void {
            $export->deleteFile();
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => DataExportStatus::class,
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Completed exports whose download window has closed.
     *
     * @param  Builder<DataExport>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('status', DataExportStatus::Completed)->where('expires_at', '<=', now());
    }

    public function isDownloadable(): bool
    {
        return $this->status === DataExportStatus::Completed
            && $this->path !== null
            && $this->expires_at?->isFuture() === true;
    }

    public function fileName(): string
    {
        return 'data-export-'.$this->created_at?->format('Y-m-d').'.zip';
    }

    public function deleteFile(): void
    {
        if ($this->disk !== null && $this->path !== null) {
            Storage::disk($this->disk)->delete($this->path);
        }
    }
}
