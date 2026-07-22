<?php

namespace App\Models;

use App\Enums\ExecutionMode;
use App\Enums\ExecutionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A workflow execution. Stored in the unified `runs` table (see Run); this
 * subclass keeps the historical column names, casts, and relations, and is
 * scoped to workflow-targeted runs so existing engine, service, and API code
 * continues to work unchanged.
 */
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
class Execution extends Run
{
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

    protected static function booted(): void
    {
        // Only ever see workflow runs through this model.
        static::addGlobalScope('workflow', function (Builder $query) {
            $query->where('runnable_type', 'workflow');
        });

        static::creating(function (Execution $execution) {
            $execution->runnable_type = 'workflow';
        });
    }

    /**
     * Backwards-compatible alias over the polymorphic runnable columns.
     */
    protected function workflowId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->runnable_type === 'workflow' ? $this->runnable_id : null,
            set: function ($value) {
                if ($value === null) {
                    return [];
                }

                return ['runnable_type' => 'workflow', 'runnable_id' => $value];
            },
        );
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'runnable_id');
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
