<?php

namespace App\Models\System;

use App\Enums\Setup\SyncStatus;
use Database\Factories\System\SetupSyncFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The record of a setup dataset's last sync: the checksum of the code it was
 * applied from, so an unchanged dataset can be skipped.
 */
#[Fillable(['dataset', 'checksum', 'version', 'status', 'rows_before', 'rows_after', 'error', 'synced_at'])]
class SetupSync extends Model
{
    /** @use HasFactory<SetupSyncFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'version' => 'integer',
            'rows_before' => 'integer',
            'rows_after' => 'integer',
            'synced_at' => 'datetime',
        ];
    }
}
