<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantActionStatus;
use App\Enums\Assistant\AssistantToolEffect;
use Database\Factories\Assistant\AssistantActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tool call the assistant wanted to make that needed the owner's say-so.
 * Keyed by the provider's `tool_call_id`, and it remembers its result, so
 * an approved call runs exactly once no matter how often the turn replays.
 */
#[Fillable(['assistant_session_id', 'assistant_turn_id', 'tool_call_id', 'tool', 'arguments', 'effect', 'reason', 'status', 'result', 'decision_note', 'decided_at', 'expires_at'])]
class AssistantAction extends Model
{
    /** @use HasFactory<AssistantActionFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'effect' => AssistantToolEffect::class,
            'status' => AssistantActionStatus::class,
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssistantSession::class, 'assistant_session_id');
    }

    public function turn(): BelongsTo
    {
        return $this->belongsTo(AssistantTurn::class, 'assistant_turn_id');
    }
}
