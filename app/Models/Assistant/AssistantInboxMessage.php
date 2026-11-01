<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantInboxDraftStatus;
use App\Enums\Assistant\AssistantInboxMessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What Smart Inbox did with one incoming email: the labels it applied,
 * whether it moved it out of the inbox, and any reply it drafted. Each
 * email is handled once.
 */
#[Fillable(['assistant_inbox_config_id', 'provider_message_id', 'thread_id', 'from', 'subject', 'snippet', 'received_at', 'status', 'labels', 'archived', 'skipped_reason', 'suggestion', 'draft_provider_id', 'draft_hash', 'draft_status', 'usage'])]
class AssistantInboxMessage extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'status' => AssistantInboxMessageStatus::class,
            'labels' => 'array',
            'archived' => 'boolean',
            'draft_status' => AssistantInboxDraftStatus::class,
            'usage' => 'array',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(AssistantInboxConfig::class, 'assistant_inbox_config_id');
    }
}
