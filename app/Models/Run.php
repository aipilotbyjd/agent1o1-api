<?php

namespace App\Models;

use App\Enums\ExecutionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Unified run record. A run is any execution of an automation — a workflow
 * execution or an agent run — stored in the `runs` table and addressed
 * polymorphically via `runnable` ('workflow' | 'agent').
 *
 * Execution and AgentRun are compatibility subclasses that expose the original
 * per-type column names and casts; new code should query Run directly for a
 * cross-type view (unified /runs API, dashboards, reporting).
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
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'trigger_data' => 'array',
            'result_data' => 'array',
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

    /**
     * The automation this run belongs to — a Workflow or an Agent.
     *
     * @return MorphTo<Model, $this>
     */
    public function runnable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isForWorkflow(): bool
    {
        return $this->runnable_type === 'workflow';
    }

    public function isForAgent(): bool
    {
        return $this->runnable_type === 'agent';
    }
}
