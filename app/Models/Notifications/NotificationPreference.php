<?php

namespace App\Models\Notifications;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Notifications\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's choice, in one workspace, for one event or a whole category of
 * them. `event_key` is an event key (`run.failed`) or a category key
 * (`category:runs`); the event's own row wins over its category's.
 *
 * Which chat or webhook channels hear about an event is the workspace's
 * decision, not a member's: see `NotificationRoute`.
 */
#[Fillable(['workspace_id', 'user_id', 'event_key', 'in_app', 'email', 'email_digest'])]
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
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
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
