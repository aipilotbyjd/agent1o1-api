<?php

namespace App\Notifications\Channels;

use App\Models\Notifications\NotificationDigestItem;
use App\Models\User;
use App\Notifications\AppNotification;

/**
 * Holds an email back for the member's daily digest instead of sending it
 * now. `notifications:send-digests` sends and clears them.
 */
class DigestChannel
{
    public function send(object $notifiable, AppNotification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        NotificationDigestItem::query()->create([
            'user_id' => $notifiable->getKey(),
            'workspace_id' => $notification->workspaceId,
            'event_key' => $notification->type->value,
            'title' => $notification->title,
            'body' => $notification->body,
            'action_url' => $notification->actionUrl(),
            'created_at' => now(),
        ]);
    }
}
