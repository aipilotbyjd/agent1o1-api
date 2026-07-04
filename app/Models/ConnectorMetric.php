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
            'date' => 'date',
            'total_calls' => 'integer',
            'success_calls' => 'integer',
            'failed_calls' => 'integer',
            'total_duration_ms' => 'integer',
        ];
    }
}
