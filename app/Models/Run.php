<?php

namespace App\Models;

use App\Enums\ExecutionMode;
use App\Enums\ExecutionStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A run of an automation — a workflow execution or an agent run — stored in the
 * unified `runs` table and addressed polymorphically via `runnable`
 * ('workflow' | 'agent'). This is the single run model; workflow-only relations
 * (nodes, checkpoint, logs, …) and agent-only relations (steps) both hang off
 * it and are simply empty for the other kind.
 */
class Run extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'runs';

    protected $guarded = [];

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
            'plan' => 'array',
            'reflections' => 'array',
            'metadata' => 'array',
            'attempt' => 'integer',
            'max_attempts' => 'integer',
            'retry_delay_seconds' => 'integer',
            'credits_consumed' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost' => 'decimal:6',
        ];
    }

    // ── Target ──────────────────────────────────────────────────────────

    /**
     * @return MorphTo<Model, $this>
     */
    public function runnable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isForWorkflow(): bool
    {
        return $this->runnable_type === 'workflow';
    }

    public function isForAgent(): bool
    {
        return $this->runnable_type === 'agent';
    }

    /** Backwards-compatible alias over the polymorphic runnable columns. */
    protected function workflowId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->runnable_type === 'workflow' ? $this->runnable_id : null,
            set: fn ($value) => $value === null ? [] : ['runnable_type' => 'workflow', 'runnable_id' => $value],
        );
    }

    /** Backwards-compatible alias over the polymorphic runnable columns. */
    protected function agentId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->runnable_type === 'agent' ? $this->runnable_id : null,
            set: fn ($value) => $value === null ? [] : ['runnable_type' => 'agent', 'runnable_id' => $value],
        );
    }

    // ── Shared relations ────────────────────────────────────────────────

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'runnable_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'runnable_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Workflow-execution relations ────────────────────────────────────

    public function nodes(): HasMany
    {
        return $this->hasMany(ExecutionNode::class, 'execution_id')->orderBy('sequence');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ExecutionLog::class, 'execution_id');
    }

    public function replayPacks(): HasMany
    {
        return $this->hasMany(ExecutionReplayPack::class, 'execution_id');
    }

    public function fixSuggestions(): HasMany
    {
        return $this->hasMany(AiFixSuggestion::class, 'execution_id');
    }

    public function checkpoint(): HasOne
    {
        return $this->hasOne(ExecutionCheckpoint::class, 'execution_id');
    }

    public function parentExecution(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'parent_execution_id');
    }

    public function childExecutions(): HasMany
    {
        return $this->hasMany(Run::class, 'parent_execution_id');
    }

    // ── Agent-run relations ─────────────────────────────────────────────

    public function steps(): HasMany
    {
        return $this->hasMany(AiAgentStep::class, 'agent_run_id')->orderBy('step_number');
    }

    /**
     * Internal-agent calls (planner, reflection, moderation, ...) made in
     * service of this run — the previously invisible "system overhead".
     *
     * @return HasMany<InternalAgentRun, $this>
     */
    public function internalRuns(): HasMany
    {
        return $this->hasMany(InternalAgentRun::class, 'parent_run_id')->orderBy('created_at');
    }

    // ── Status helpers ──────────────────────────────────────────────────

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
