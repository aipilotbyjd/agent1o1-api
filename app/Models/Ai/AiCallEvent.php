<?php

namespace App\Models\Ai;

use App\Models\Workspaces\Workspace;
use Database\Factories\Ai\AiCallEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request sent to an AI provider, with what it cost the platform. See
 * the migration; written only by `AiCallRecorder`.
 */
#[Fillable([
    'occurred_at', 'workspace_id', 'user_id', 'feature', 'agent_class', 'kind', 'invocation_id', 'step',
    'run_id', 'subject_type', 'subject_id', 'trace_id', 'provider', 'model', 'is_byok', 'status',
    'error_class', 'error_message', 'latency_ms', 'input_tokens', 'cache_read_tokens', 'cache_write_tokens',
    'output_tokens', 'reasoning_tokens', 'units', 'provider_cost_usd', 'price_status', 'price_version_id',
])]
class AiCallEvent extends Model
{
    /** @use HasFactory<AiCallEventFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    public const string STATUS_OK = 'ok';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_FAILED_OVER = 'failed_over';

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'step' => 'integer',
            'is_byok' => 'boolean',
            'latency_ms' => 'integer',
            'input_tokens' => 'integer',
            'cache_read_tokens' => 'integer',
            'cache_write_tokens' => 'integer',
            'output_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'units' => 'integer',
            'provider_cost_usd' => 'float',
        ];
    }
}
