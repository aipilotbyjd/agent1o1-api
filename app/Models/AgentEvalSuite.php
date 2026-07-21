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
    'created_by',
    'name',
    'description',
])]
class AgentEvalSuite extends Model
{
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return HasMany<AgentEvalCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(AgentEvalCase::class, 'suite_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<AgentEvalRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AgentEvalRun::class, 'suite_id')->latest();
    }
}
