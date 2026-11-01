<?php

namespace App\Models\Agents;

use App\Enums\Agents\EvalRunStatus;
use App\Enums\Agents\EvalRunTrigger;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\AgentEvalRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One execution of a suite. Like `AgentSession`, this is a `runnable`
 * (`runs.runnable_type = AgentEvalRun::class`) so grading shows up in the
 * workspace's run list and is metered through the same ledger as everything
 * else: an eval that quietly called a model twenty times without appearing
 * anywhere would be the one kind of spend nobody could account for.
 *
 * `status`/`passed`/`failed`/`regressed`/timestamps are written by `EvalRunner`, not
 * mass-assigned: same convention as `Run` and `NodeRun`.
 */
#[Fillable(['workspace_id', 'agent_eval_suite_id', 'agent_version_id', 'trigger', 'triggered_by'])]
class AgentEvalRun extends Model
{
    /** @use HasFactory<AgentEvalRunFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'trigger' => 'manual',
        'passed' => 0,
        'failed' => 0,
        'regressed' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EvalRunStatus::class,
            'trigger' => EvalRunTrigger::class,
            'regressed' => 'boolean',
            'passed' => 'integer',
            'failed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function suite(): BelongsTo
    {
        return $this->belongsTo(AgentEvalSuite::class, 'agent_eval_suite_id');
    }

    /**
     * The agent behavior that was graded: see the migration's docblock.
     */
    public function agentVersion(): BelongsTo
    {
        return $this->belongsTo(AgentVersion::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AgentEvalCaseResult::class);
    }

    public function runs(): MorphMany
    {
        return $this->morphMany(Run::class, 'runnable');
    }
}
