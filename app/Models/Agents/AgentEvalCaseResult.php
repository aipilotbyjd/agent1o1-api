<?php

namespace App\Models\Agents;

use Database\Factories\Agents\AgentEvalCaseResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the agent answered for one case, and how each assertion graded.
 * `passed`/`output`/`usage` are engine-written (`EvalRunner`), so they stay
 * out of the fillable list.
 */
#[Fillable(['agent_eval_run_id', 'agent_eval_case_id'])]
class AgentEvalCaseResult extends Model
{
    /** @use HasFactory<AgentEvalCaseResultFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'passed' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'assertions' => 'array',
            'usage' => 'array',
        ];
    }

    public function evalRun(): BelongsTo
    {
        return $this->belongsTo(AgentEvalRun::class, 'agent_eval_run_id');
    }

    public function evalCase(): BelongsTo
    {
        return $this->belongsTo(AgentEvalCase::class, 'agent_eval_case_id');
    }
}
