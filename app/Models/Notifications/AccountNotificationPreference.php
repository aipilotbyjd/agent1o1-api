<?php

namespace App\Models\Notifications;

use App\Models\User;
use Database\Factories\Notifications\AccountNotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's own choice for an account-level event (`NotificationScope::Account`)
 * or a whole category of them. `key` is an event key (`assistant.daily_report`)
 * or a category key (`category:assistant`); the event's own row wins.
 */
#[Fillable(['user_id', 'key', 'in_app', 'email', 'email_digest'])]
class AccountNotificationPreference extends Model
{
    /** @use HasFactory<AccountNotificationPreferenceFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'email' => 'boolean',
            'email_digest' => 'boolean',
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
