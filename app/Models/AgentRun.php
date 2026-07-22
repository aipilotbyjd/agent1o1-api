<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'agent_id',
    'workspace_id',
    'user_id',
    'conversation_id',
    'trigger_id',
    'source',
    'status',
    'input',
    'output',
    'plan',
    'reflections',
    'error',
    'provider',
    'model',
    'prompt_tokens',
    'completion_tokens',
    'total_tokens',
    'estimated_cost',
    'duration_ms',
    'metadata',
    'started_at',
    'finished_at',
])]
class AgentRun extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost' => 'decimal:6',
            'duration_ms' => 'integer',
            'metadata' => 'array',
            'plan' => 'array',
            'reflections' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AiAgentStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(AiAgentStep::class)->orderBy('step_number');
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
}
