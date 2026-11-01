<?php

namespace App\Models\Agents;

use App\Enums\Agents\AgentMessageRole;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Artifacts\Artifact;
use App\Models\Billing\CreditTransaction;
use Database\Factories\Agents\AgentMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * `usage` (token/credit accounting, same shape as `node_runs.usage`) is
 * engine-managed, not in `#[Fillable]`, written via `forceFill()` after
 * create(), mirroring `NodeRun`'s own convention. So is `paused_state`: set
 * by `AgentRunner` while the turn waits on an approval, cleared when it
 * resumes: see `Services\Agents\Approvals\PausedTurn`.
 */
#[Fillable(['agent_session_id', 'role', 'content', 'skill_id', 'tool_calls', 'tool_results', 'tool_call_id'])]
class AgentMessage extends Model
{
    /** @use HasFactory<AgentMessageFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AgentMessageRole::class,
            'tool_calls' => 'array',
            'tool_results' => 'array',
            'paused_state' => 'array',
            'usage' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'agent_session_id');
    }

    /**
     * The skill the person picked for this (user) message, if any.
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /**
     * Files a member sent along with this (user) message. Stored as
     * `Artifact`s and handed to the model with the message on every turn:
     * see `AgentRunner::openTurn()` and `WorkspaceAgent::messages()`.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    /**
     * The calls this (assistant) turn paused on for approval: see
     * `Models\Agents\AgentAction`.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(AgentAction::class);
    }

    /**
     * The `CreditTransaction` this turn was billed under: see
     * `NodeRun::creditTransaction()`'s docblock for why this isn't a real
     * `morphOne`.
     */
    public function creditTransaction(): HasOne
    {
        return $this->hasOne(CreditTransaction::class, 'source_id')
            ->where('source_type', CreditTransactionType::AgentStep);
    }
}
