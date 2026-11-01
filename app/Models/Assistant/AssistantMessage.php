<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantMessageRole;
use Database\Factories\Assistant\AssistantMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A message in an `AssistantSession`. `compacted_into_id` points at the
 * `Recap` message that replaced this one in the model's context; the row
 * itself stays so the full transcript is still shown to the user.
 */
#[Fillable(['assistant_session_id', 'role', 'content', 'tool_calls', 'tool_call_id', 'tool_results', 'attachments', 'usage', 'paused_state', 'compacted_into_id'])]
class AssistantMessage extends Model
{
    /** @use HasFactory<AssistantMessageFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AssistantMessageRole::class,
            'tool_calls' => 'array',
            'tool_results' => 'array',
            'attachments' => 'array',
            'usage' => 'array',
            'paused_state' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssistantSession::class, 'assistant_session_id');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(AssistantFeedback::class);
    }

    public function compactedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'compacted_into_id');
    }
}
