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

#[Fillable(['workspace_id', 'user_id', 'event_key', 'in_app', 'email', 'channel_ids'])]
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'email' => 'boolean',
            'channel_ids' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
