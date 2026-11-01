<?php

namespace App\Models\Agents;

use App\Enums\Agents\AgentPlanStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\AgentPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A plan proposed by an agent in Plan mode: see `Ai\Tools\SubmitPlanTool`.
 * Each step is `{id, tool, summary, arguments, status}`: `arguments` holds
 * the key values the step commits to (a recipient, a channel), and a call
 * only counts as that step when it matches them. `status` and the decision
 * fields are engine-managed, like `AgentAction`'s.
 */
#[Fillable(['workspace_id', 'agent_id', 'agent_session_id', 'run_id', 'title', 'summary', 'steps'])]
class AgentPlan extends Model
{
    /** @use HasFactory<AgentPlanFactory> */
    use HasFactory, HasUuids;

    public const string STEP_PENDING = 'pending';

    public const string STEP_DONE = 'done';

    public const string STEP_SKIPPED = 'skipped';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'proposed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgentPlanStatus::class,
            'steps' => 'array',
            'decided_at' => 'datetime',
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'agent_session_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AgentAction::class, 'plan_id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingSteps(): array
    {
        return array_values(array_filter($this->steps ?? [], fn (array $step): bool => ($step['status'] ?? self::STEP_PENDING) === self::STEP_PENDING));
    }
}
