<?php

namespace App\Models\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An email held back for a member's daily digest. Deleted once the digest
 * that included it is sent.
 */
#[Fillable(['user_id', 'workspace_id', 'event_key', 'title', 'body', 'action_url', 'created_at'])]
class NotificationDigestItem extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
