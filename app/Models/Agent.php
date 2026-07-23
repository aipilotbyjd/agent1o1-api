<?php

namespace App\Models;

use App\Contracts\Automatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Ai\Models\Conversation;

#[Fillable([
    'workspace_id',
    'type',
    'created_by',
    'name',
    'slug',
    'description',
    'instructions',
    'model',
    'provider',
    'max_steps',
    'timeout_seconds',
    'is_active',
    'category',
    'metadata',
    'default_workflow_id',
    // Phase 1 — intelligence & reasoning
    'planning_enabled',
    'reflection_enabled',
    'reflection_interval',
    'child_agent_ids',
    'memory_auto_extract',
    'memory_semantic_recall',
    'memory_recall_limit',
    // Phase 2 — tooling & integrations
    'code_execution_enabled',
    'web_browsing_enabled',
    'tool_cache_enabled',
    // Phase 3 — ops & reliability
    'guardrails',
    'max_tokens_per_run',
    'daily_token_budget',
    'daily_cost_budget',
    'is_paused',
    'paused_reason',
])]
class Agent extends Model implements Automatable
{
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_steps' => 'integer',
            'timeout_seconds' => 'integer',
            'metadata' => 'array',
            'created_by' => 'integer',
            'planning_enabled' => 'boolean',
            'reflection_enabled' => 'boolean',
            'reflection_interval' => 'integer',
            'child_agent_ids' => 'array',
            'memory_auto_extract' => 'boolean',
            'memory_semantic_recall' => 'boolean',
            'memory_recall_limit' => 'integer',
            'code_execution_enabled' => 'boolean',
            'web_browsing_enabled' => 'boolean',
            'tool_cache_enabled' => 'boolean',
            'guardrails' => 'array',
            'max_tokens_per_run' => 'integer',
            'daily_token_budget' => 'integer',
            'daily_cost_budget' => 'decimal:4',
            'is_paused' => 'boolean',
        ];
    }

    /**
     * The consolidated, grouped view of this agent's feature settings. This is
     * the canonical API shape; the flat columns remain the storage layer. Kept
     * in sync with settingsToAttributes() below.
     *
     * @return array<string, mixed>
     */
    public function getSettingsAttribute(): array
    {
        return [
            'reasoning' => [
                'planning_enabled' => (bool) $this->planning_enabled,
                'reflection_enabled' => (bool) $this->reflection_enabled,
                'reflection_interval' => $this->reflection_interval,
                'child_agent_ids' => $this->child_agent_ids ?? [],
            ],
            'memory' => [
                'auto_extract' => (bool) $this->memory_auto_extract,
                'semantic_recall' => (bool) $this->memory_semantic_recall,
                'recall_limit' => $this->memory_recall_limit,
            ],
            'tools' => [
                'code_execution_enabled' => (bool) $this->code_execution_enabled,
                'web_browsing_enabled' => (bool) $this->web_browsing_enabled,
                'cache_enabled' => (bool) $this->tool_cache_enabled,
            ],
            'limits' => [
                'max_steps' => $this->max_steps,
                'timeout_seconds' => $this->timeout_seconds,
                'max_tokens_per_run' => $this->max_tokens_per_run,
                'daily_token_budget' => $this->daily_token_budget,
                'daily_cost_budget' => $this->daily_cost_budget !== null ? (float) $this->daily_cost_budget : null,
            ],
            'guardrails' => $this->guardrails,
        ];
    }

    /**
     * Map a (partial) nested settings payload to the flat storage columns.
     * The inverse of getSettingsAttribute(); unknown keys are ignored.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function settingsToAttributes(array $settings): array
    {
        $map = [
            'reasoning.planning_enabled' => 'planning_enabled',
            'reasoning.reflection_enabled' => 'reflection_enabled',
            'reasoning.reflection_interval' => 'reflection_interval',
            'reasoning.child_agent_ids' => 'child_agent_ids',
            'memory.auto_extract' => 'memory_auto_extract',
            'memory.semantic_recall' => 'memory_semantic_recall',
            'memory.recall_limit' => 'memory_recall_limit',
            'tools.code_execution_enabled' => 'code_execution_enabled',
            'tools.web_browsing_enabled' => 'web_browsing_enabled',
            'tools.cache_enabled' => 'tool_cache_enabled',
            'limits.max_steps' => 'max_steps',
            'limits.timeout_seconds' => 'timeout_seconds',
            'limits.max_tokens_per_run' => 'max_tokens_per_run',
            'limits.daily_token_budget' => 'daily_token_budget',
            'limits.daily_cost_budget' => 'daily_cost_budget',
            'guardrails' => 'guardrails',
        ];

        $attributes = [];

        foreach ($map as $path => $column) {
            $value = data_get($settings, $path, '__missing__');
            if ($value !== '__missing__') {
                $attributes[$column] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Sub-agents this agent may delegate to as tools (roadmap item 3).
     *
     * @return Collection<int, Agent>
     */
    public function childAgents(): Collection
    {
        $ids = $this->child_agent_ids ?? [];

        if ($ids === []) {
            return new Collection;
        }

        return static::query()
            ->where('workspace_id', $this->workspace_id)
            ->whereKey($ids)
            ->where('is_active', true)
            ->whereKeyNot($this->getKey())
            ->get();
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AgentToolConfig, $this>
     */
    public function toolConfigs(): HasMany
    {
        return $this->hasMany(AgentToolConfig::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<AgentSkill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(AgentSkill::class, 'agent_agent_skill', 'agent_id', 'skill_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Triggers targeting this agent. Unified with the workflow trigger system:
     * a Trigger's polymorphic `target` points at either a Workflow or an Agent.
     *
     * @return MorphMany<Trigger, $this>
     */
    public function triggers(): MorphMany
    {
        return $this->morphMany(Trigger::class, 'target');
    }

    /**
     * Chat conversations held with this agent (stored in `agent_conversations`).
     *
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'agent_id');
    }

    /**
     * @return HasMany<Run, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(Run::class, 'runnable_id');
    }

    /**
     * @return HasMany<AgentKnowledge, $this>
     */
    public function knowledge(): HasMany
    {
        return $this->hasMany(AgentKnowledge::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<AgentMemory, $this>
     */
    public function memories(): HasMany
    {
        return $this->hasMany(AgentMemory::class);
    }

    /**
     * @return HasMany<AgentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(AgentVersion::class)->orderByDesc('version');
    }

    /**
     * @return HasMany<AgentEvalSuite, $this>
     */
    public function evalSuites(): HasMany
    {
        return $this->hasMany(AgentEvalSuite::class);
    }
}
