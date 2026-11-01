<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantFollowUpStatus;
use Database\Factories\Assistant\AssistantFollowUpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An email thread the owner is waiting on: when `remind_at` passes with no
 * answer in the thread, the owner is reminded (`FollowUpChecker`).
 */
#[Fillable(['assistant_id', 'assistant_session_id', 'thread_id', 'subject', 'note', 'status', 'remind_at', 'resolved_at'])]
class AssistantFollowUp extends Model
{
    /** @use HasFactory<AssistantFollowUpFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssistantFollowUpStatus::class,
            'remind_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssistantSession::class, 'assistant_session_id');
    }

    public function isPending(): bool
    {
        return $this->status === AssistantFollowUpStatus::Pending;
    }
}
