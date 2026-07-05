<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'workspace_id',
    'connector',
    'date',
    'total_calls',
    'success_calls',
    'failed_calls',
    'total_duration_ms',
])]
class ConnectorMetric extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            // Store as a bare date (Y-m-d). The default `date` cast serializes to
            // `Y-m-d H:i:s`, so the persisted value ("...00:00:00") never matched the
            // Y-m-d string used in the daily-rollup firstOrCreate() lookup — every
            // record() then tried to re-INSERT and hit the unique constraint.
            'date' => 'date:Y-m-d',
            'total_calls' => 'integer',
            'success_calls' => 'integer',
            'failed_calls' => 'integer',
            'total_duration_ms' => 'integer',
        ];
    }
}
