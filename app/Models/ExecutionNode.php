<?php

namespace App\Models;

use App\Enums\ExecutionNodeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'execution_id',
    'node_id',
    'node_run_key',
    'node_type',
    'node_name',
    'status',
    'started_at',
    'finished_at',
    'duration_ms',
    'attempt',
    'input_data',
    'output_data',
    'error',
    'sequence',
    'loop_index',
    'parent_frame',
])]
class ExecutionNode extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => ExecutionNodeStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'attempt' => 'integer',
            'input_data' => 'array',
            'output_data' => 'array',
            'error' => 'array',
            'sequence' => 'integer',
            'loop_index' => 'integer',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(Execution::class);
    }
}
