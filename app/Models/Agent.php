<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
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
    'metadata',
    'default_workflow_id',
])]
class Agent extends Model
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
        ];
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
     * @return HasMany<AgentTrigger, $this>
     */
    public function triggers(): HasMany
    {
        return $this->hasMany(AgentTrigger::class);
    }
}
