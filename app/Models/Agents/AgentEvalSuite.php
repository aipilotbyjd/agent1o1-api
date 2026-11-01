<?php

namespace App\Models\Agents;

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
    use HasFactory, HasUuids;

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

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(AgentEvalCase::class)->orderBy('sort_order')->orderBy('id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AgentEvalRun::class);
    }
}
