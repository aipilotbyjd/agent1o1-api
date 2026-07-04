<?php

namespace App\Models;

use Database\Factories\UsageDailySnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(UsageDailySnapshotFactory::class)]
#[Fillable([
    'workspace_id',
    'snapshot_date',
    'credits_used',
    'executions_total',
    'executions_succeeded',
    'executions_failed',
    'nodes_executed',
    'ai_nodes_executed',
])]
class UsageDailySnapshot extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'credits_used' => 'integer',
            'executions_total' => 'integer',
            'executions_succeeded' => 'integer',
            'executions_failed' => 'integer',
            'nodes_executed' => 'integer',
            'ai_nodes_executed' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
