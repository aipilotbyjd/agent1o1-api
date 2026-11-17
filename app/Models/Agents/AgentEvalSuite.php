<?php

namespace App\Models\Agents;

use App\Models\Concerns\ResolvesScopedRouteBindings;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\AgentEvalSuiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A saved set of test cases for one `Agent`: see docs/AGENTS_PLAN.md's
 * "Evals" section.
 */
#[Fillable(['workspace_id', 'agent_id', 'name', 'description', 'run_on_change', 'created_by'])]
class AgentEvalSuite extends Model
{
    /** @use HasFactory<AgentEvalSuiteFactory> */
    use HasFactory, HasUuids, ResolvesScopedRouteBindings;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'run_on_change' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'run_on_change' => 'boolean',
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
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function scopedRouteBindingRelations(): array
    {
        return ['evalRun' => 'runs'];
    }

    /**
     * @return HasMany<AgentEvalCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(AgentEvalCase::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<AgentEvalRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AgentEvalRun::class);
    }
}
