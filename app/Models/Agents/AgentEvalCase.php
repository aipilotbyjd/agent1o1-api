<?php

namespace App\Models\Agents;

use Database\Factories\Agents\AgentEvalCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One prompt and the expectations its answer must satisfy. `assertions` is a
 * list of `{type, value}` pairs: see `EvalAssertionType`.
 */
#[Fillable(['agent_eval_suite_id', 'name', 'input', 'assertions', 'sort_order'])]
class AgentEvalCase extends Model
{
    /** @use HasFactory<AgentEvalCaseFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assertions' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function suite(): BelongsTo
    {
        return $this->belongsTo(AgentEvalSuite::class, 'agent_eval_suite_id');
    }
}
