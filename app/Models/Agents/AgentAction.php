<?php

namespace App\Models\Agents;

use App\Enums\Agents\ActionEffect;
use App\Enums\Agents\ActionRisk;
use App\Enums\Agents\ActionToolKind;
use App\Enums\Agents\ActionVerdict;
use App\Enums\Agents\AgentActionStatus;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\AgentActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One side-effecting tool call an agent made or tried to make: see the
 * `agent_actions` migration. Written by `ActionGate` when the call is
 * weighed; `status` and the decision fields are engine-managed (set through
 * `forceFill()` by the gate, `ResolveAgentActionsAction` and
 * `AgentActionExecutor`), never mass-assigned from a request.
 */
#[Fillable([
    'workspace_id', 'agent_id', 'agent_session_id', 'run_id', 'node_run_id', 'agent_message_id', 'plan_id',
    'tool_call_id', 'tool_name', 'tool_kind', 'effect', 'arguments', 'outcome', 'reason', 'risk', 'review',
    'approvers', 'requested_at', 'expires_at',
])]
class AgentAction extends Model
{
    /** @use HasFactory<AgentActionFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tool_kind' => ActionToolKind::class,
            'effect' => ActionEffect::class,
            'outcome' => ActionVerdict::class,
            'status' => AgentActionStatus::class,
            'risk' => ActionRisk::class,
            'arguments' => 'array',
            'edited_arguments' => 'array',
            'reason' => 'array',
            'review' => 'array',
            'approvers' => 'array',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
            'executed_at' => 'datetime',
            'stops_turn' => 'boolean',
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

    public function nodeRun(): BelongsTo
    {
        return $this->belongsTo(NodeRun::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AgentMessage::class, 'agent_message_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AgentPlan::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * The arguments the call runs with: a reviewer's edit when there is one,
     * otherwise what the model sent.
     *
     * @return array<string, mixed>
     */
    public function effectiveArguments(): array
    {
        return $this->edited_arguments ?? $this->arguments ?? [];
    }

    /**
     * Whether a tool rule named who may decide this call. Only those people
     * (and the workspace's owners and admins) can, so it can't be decided
     * where the decider isn't a known account here: a chat button or an
     * API key.
     */
    public function hasNamedApprovers(): bool
    {
        return ($this->approvers ?? []) !== [];
    }

    /**
     * Whether a person was asked about this call: decided or not. A
     * resumed turn has to hand the SDK a decision for every one of these.
     */
    public function wasAsked(): bool
    {
        return $this->outcome === ActionVerdict::Ask;
    }
}
