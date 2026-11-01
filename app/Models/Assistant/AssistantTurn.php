<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantTurnStatus;
use Database\Factories\Assistant\AssistantTurnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One run of the assistant for one user message: queued, run by
 * `RunAssistantTurnJob`, possibly paused for approvals, then finished.
 * A turn that pauses and resumes stays one turn with one reply message.
 */
#[Fillable(['assistant_session_id', 'user_message_id', 'assistant_message_id', 'status', 'error', 'usage', 'cancel_requested_at', 'started_at', 'finished_at'])]
class AssistantTurn extends Model
{
    /** @use HasFactory<AssistantTurnFactory> */
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
            'status' => AssistantTurnStatus::class,
            'usage' => 'array',
            'cancel_requested_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssistantSession::class, 'assistant_session_id');
    }

    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(AssistantMessage::class, 'user_message_id');
    }

    public function assistantMessage(): BelongsTo
    {
        return $this->belongsTo(AssistantMessage::class, 'assistant_message_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AssistantAction::class);
    }

    public function isCancelRequested(): bool
    {
        return $this->newQuery()->whereKey($this->getKey())->whereNotNull('cancel_requested_at')->exists();
    }
}
