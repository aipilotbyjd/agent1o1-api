<?php

namespace App\Models\Agents;

use App\Enums\Agents\AgentActionStatus;
use App\Enums\Agents\AgentSessionStatus;
use App\Enums\Agents\AutonomyMode;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\AgentSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A conversation thread with an `Agent`: a `runnable` itself
 * (`runs.runnable_type = AgentSession::class`), one `Run` per turn, per
 * docs/WORKFLOWS_PLAN.md's `runs` table note on why agent invocations and
 * workflow executions share one table rather than two.
 */
/*
 * `status` is user-facing (a person archives their own conversation),
 * unlike `Run.status`, which is engine-managed and deliberately left out of
 * its own fillable list.
 */
#[Fillable(['workspace_id', 'agent_id', 'agent_version_id', 'user_id', 'title', 'status', 'autonomy_mode', 'test_mode'])]
class AgentSession extends Model
{
    /** @use HasFactory<AgentSessionFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgentSessionStatus::class,
            'last_activity_at' => 'datetime',
            'autonomy_mode' => AutonomyMode::class,
            'test_mode' => 'boolean',
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

    /**
     * The agent behavior this conversation started with: see the
     * `agent_version_id` migration.
     */
    public function agentVersion(): BelongsTo
    {
        return $this->belongsTo(AgentVersion::class);
    }

    /**
     * The agent as this conversation should see it: the live model with the
     * pinned version's behavior applied over it, so an instruction edit
     * mid-conversation can't change how the assistant answers the next turn.
     *
     * Returned **unsaved** (`forceFill()` without `save()`) so this is a
     * read-time view, never a write back onto the agent. Relations (skills,
     * knowledge, tool bindings) are deliberately left live; see
     * `AgentVersioner`'s docblock for why an old snapshot doesn't get to
     * decide which tools are attached today.
     *
     * The snapshot is applied to a `clone`, not to `$this->agent` itself.
     * Filling the loaded relation would leave the session's own `agent`
     * carrying pinned values as dirty attributes, so any later `save()` on
     * it (anywhere in the same request) would quietly persist a months-old
     * snapshot over the live agent. Cloning keeps the relations shared (so
     * they stay live, as intended) while the attributes are the clone's own.
     */
    public function pinnedAgent(): Agent
    {
        $agent = $this->agent;
        $snapshot = $this->agentVersion?->snapshot;

        if ($snapshot === null) {
            return $agent;
        }

        return (clone $agent)->forceFill([
            'instructions' => array_key_exists('instructions', $snapshot) ? $snapshot['instructions'] : $agent->instructions,
            'provider' => $snapshot['provider'] ?? $agent->provider,
            'model' => $snapshot['model'] ?? null,
            'temperature' => $snapshot['temperature'] ?? null,
            'settings' => $snapshot['settings'] ?? null,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The conversation that handed this one its task, when a subagent runs it.
     */
    public function parentSession(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_session_id');
    }

    /**
     * Subagents this conversation started: see `InvokeAgentTool`.
     */
    public function subagentTasks(): HasMany
    {
        return $this->hasMany(SubagentTask::class, 'parent_session_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class);
    }

    public function runs(): MorphMany
    {
        return $this->morphMany(Run::class, 'runnable');
    }

    /**
     * The latest automatic QA grading of this conversation: see
     * `Services\Agents\SessionEvaluator`. A `HasOne` because a session that
     * continues and completes again replaces its evaluation rather than
     * accumulating one per turn.
     */
    public function evaluation(): HasOne
    {
        return $this->hasOne(AgentSessionEvaluation::class);
    }

    /**
     * Tool calls made in this conversation: see `Models\Agents\AgentAction`.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(AgentAction::class);
    }

    /**
     * Actions in this conversation still waiting for someone to decide.
     */
    public function pendingActions(): HasMany
    {
        return $this->actions()->where('status', AgentActionStatus::Pending);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(AgentPlan::class);
    }
}
