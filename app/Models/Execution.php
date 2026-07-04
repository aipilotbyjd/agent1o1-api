<?php

namespace App\Models;

use App\Enums\ExecutionMode;
use App\Enums\ExecutionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'workflow_id',
    'workspace_id',
    'status',
    'wait_token',
    'mode',
    'triggered_by',
    'started_at',
    'finished_at',
    'duration_ms',
    'trigger_data',
    'result_data',
    'error',
    'attempt',
    'max_attempts',
    'retry_delay_seconds',
    'parent_execution_id',
    'credits_consumed',
])]
class Execution extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => ExecutionStatus::class,
            'mode' => ExecutionMode::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'trigger_data' => 'array',
            'result_data' => 'array',
            'error' => 'array',
            'attempt' => 'integer',
            'max_attempts' => 'integer',
            'retry_delay_seconds' => 'integer',
            'credits_consumed' => 'integer',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(ExecutionNode::class)->orderBy('sequence');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ExecutionLog::class);
    }

    public function replayPacks(): HasMany
    {
        return $this->hasMany(ExecutionReplayPack::class);
    }

    public function fixSuggestions(): HasMany
    {
        return $this->hasMany(AiFixSuggestion::class);
    }

    public function checkpoint(): HasOne
    {
        return $this->hasOne(ExecutionCheckpoint::class);
    }

    public function parentExecution(): BelongsTo
    {
        return $this->belongsTo(Execution::class, 'parent_execution_id');
    }

    public function childExecutions(): HasMany
    {
        return $this->hasMany(Execution::class, 'parent_execution_id');
    }

    public function isPending(): bool
    {
        return $this->status === ExecutionStatus::Pending;
    }

    public function isRunning(): bool
    {
        return $this->status === ExecutionStatus::Running;
    }

    public function isCompleted(): bool
    {
        return $this->status === ExecutionStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === ExecutionStatus::Failed;
    }

    public function isWaiting(): bool
    {
        return $this->status === ExecutionStatus::Waiting;
    }
}
