<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'execution_id',
    'execution_node_key',
    'step_number',
    'action',
    'tool_name',
    'tool_input',
    'tool_output',
    'llm_reasoning',
    'tokens_used',
    'duration_ms',
])]
class AiAgentStep extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'tool_input' => 'array',
            'tool_output' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Execution, $this>
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(Execution::class);
    }
}
