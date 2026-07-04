<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'execution_id',
    'graph_snapshot',
    'context_snapshot',
    'output_buffer_snapshot',
    'frontier_snapshot',
])]
class ExecutionCheckpoint extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'graph_snapshot' => 'array',
            'context_snapshot' => 'array',
            'output_buffer_snapshot' => 'array',
            'frontier_snapshot' => 'array',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(Execution::class);
    }
}
