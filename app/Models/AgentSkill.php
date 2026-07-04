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
    'is_shared',
    'version',
])]
class AgentSkill extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'version' => 'integer',
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
     * @return HasMany<AgentSkillReference, $this>
     */
    public function references(): HasMany
    {
        return $this->hasMany(AgentSkillReference::class, 'skill_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<AgentSkillScript, $this>
     */
    public function scripts(): HasMany
    {
        return $this->hasMany(AgentSkillScript::class, 'skill_id');
    }

    /**
     * @return BelongsToMany<Agent, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_agent_skill', 'skill_id', 'agent_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}
