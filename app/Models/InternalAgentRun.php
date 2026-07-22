<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded LLM call by a platform-owned (internal) agent — planner,
 * moderation, eval judge, etc. Attributed to the user-agent run it served via
 * parent_run_id so previously invisible "system overhead" spend shows up in
 * run details and analytics.
 */
#[Fillable([
    'name',
    'version',
    'parent_run_id',
    'workspace_id',
    'provider',
    'model',
    'prompt_tokens',
    'completion_tokens',
    'total_tokens',
    'estimated_cost',
    'duration_ms',
    'status',
    'error',
])]
class InternalAgentRun extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost' => 'decimal:6',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AgentRun, $this>
     */
    public function parentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'parent_run_id');
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
