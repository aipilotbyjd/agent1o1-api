<?php

namespace App\Models\Ai;

use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day of one workspace's calls to one model (see the migration).
 */
#[Fillable(['date', 'workspace_id', 'provider', 'model', 'own_key', 'calls', 'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens', 'reasoning_tokens', 'prompt_tokens', 'completion_tokens', 'estimated_cost_usd'])]
class ModelUsageDaily extends Model
{
    use HasUuids;

    protected $table = 'model_usage_daily';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'own_key' => 'boolean',
            'calls' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_read_tokens' => 'integer',
            'cache_write_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'estimated_cost_usd' => 'float',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
