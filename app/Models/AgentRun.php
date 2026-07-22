<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An agent run. Stored in the unified `runs` table (see Run); this subclass
 * keeps the historical column names, casts, and relations, and is scoped to
 * agent-targeted runs so existing agent runtime and API code works unchanged.
 */
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
class AgentRun extends Run
{
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

    protected static function booted(): void
    {
        static::addGlobalScope('agent', function (Builder $query) {
            $query->where('runnable_type', 'agent');
        });

        static::saving(function (AgentRun $run) {
            $run->runnable_type = 'agent';

            if ($run->agent_id && ! $run->runnable_id) {
                $run->runnable_id = $run->agent_id;
            }

            if ($run->runnable_id && ! $run->agent_id) {
                $run->agent_id = $run->runnable_id;
            }
        });
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
}
