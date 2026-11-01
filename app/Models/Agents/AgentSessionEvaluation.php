<?php

namespace App\Models\Agents;

use App\Enums\Agents\SessionEvaluationGrade;
use App\Enums\Agents\SessionEvaluationStatus;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Billing\CreditTransaction;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\AgentSessionEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The latest automatic QA grading of one `AgentSession`: see
 * `Services\Agents\SessionEvaluator` and the migration's docblock.
 * `status`/`grade`/result columns are engine-managed: written by
 * `SessionEvaluator` via `forceFill()`, never mass-assigned, same convention
 * as `AgentEvalRun`.
 */
#[Fillable(['workspace_id', 'agent_id', 'agent_session_id'])]
class AgentSessionEvaluation extends Model
{
    /** @use HasFactory<AgentSessionEvaluationFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SessionEvaluationStatus::class,
            'grade' => SessionEvaluationGrade::class,
            'criteria_results' => 'array',
            'data_results' => 'array',
            'applied_tags' => 'array',
            'usage' => 'array',
            'evaluated_at' => 'datetime',
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

    public function runs(): MorphMany
    {
        return $this->morphMany(Run::class, 'runnable');
    }

    /**
     * The `CreditTransaction` this grading was billed under: see
     * `NodeRun::creditTransaction()`'s docblock for why this isn't a real
     * `morphOne`.
     */
    public function creditTransaction(): HasOne
    {
        return $this->hasOne(CreditTransaction::class, 'source_id')
            ->where('source_type', CreditTransactionType::SessionEvaluation);
    }
}
