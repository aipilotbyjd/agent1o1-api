<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantInboxDraftMode;
use App\Models\Connectors\ConnectorCredential;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The owner's Smart Inbox: which mailbox it watches, how it drafts, and
 * how far it has read. One per assistant: connecting another mailbox
 * replaces the first.
 */
#[Fillable(['assistant_id', 'provider', 'connector_credential_id', 'enabled', 'draft_mode', 'drafting_instructions', 'known_senders_only', 'skip_existing_labels', 'enabled_at', 'last_checked_at', 'last_error'])]
class AssistantInboxConfig extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'draft_mode' => AssistantInboxDraftMode::class,
            'known_senders_only' => 'boolean',
            'skip_existing_labels' => 'boolean',
            'enabled_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(ConnectorCredential::class, 'connector_credential_id');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(AssistantInboxLabel::class)->orderBy('position');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AssistantInboxMessage::class);
    }
}
