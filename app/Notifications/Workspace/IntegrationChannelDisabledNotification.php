<?php

namespace App\Notifications\Workspace;

use App\Notifications\AppNotification;
use App\Enums\Notifications\NotificationType;
use App\Models\Notifications\NotificationChannel;

/**
 * A chat or webhook channel failed too many deliveries in a row and was
 * switched off, so the workspace stops sending into a dead end. Sent to its
 * owners and admins (`DeliverIntegrationJob`).
 */
class IntegrationChannelDisabledNotification extends AppNotification
{
    public function __construct(NotificationChannel $channel)
    {
        parent::__construct(
            workspace: $channel->workspace,
            type: NotificationType::IntegrationChannelDisabled,
            title: "The {$channel->type} channel \"{$channel->name}\" was switched off",
            body: "It failed {$channel->consecutive_failures} deliveries in a row"
                .($channel->last_error !== null ? " (last error: {$channel->last_error})" : '')
                .'. Check its address, then switch it back on.',
            data: [
                'notification_channel_id' => $channel->id,
                'channel_type' => $channel->type,
                'disabled_at' => $channel->disabled_at?->toIso8601String(),
            ],
        );
    }

    /**
     * A channel is switched off once per run of failures.
     */
    public function idempotencyKey(): ?string
    {
        return 'channel-disabled:'.$this->data['notification_channel_id'].':'.($this->data['disabled_at'] ?? '');
    }
}
