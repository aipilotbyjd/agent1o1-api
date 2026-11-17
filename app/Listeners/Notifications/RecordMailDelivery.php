<?php

namespace App\Listeners\Notifications;

use App\Enums\Notifications\DeliveryStatus;
use App\Models\Notifications\NotificationDelivery;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records every notification email in `notification_deliveries`, next to
 * the chat and webhook deliveries, so the admin's health page shows every
 * outbound message. Addresses are masked: the log says that an email went,
 * not who to.
 */
class RecordMailDelivery
{
    public function handleSent(NotificationSent $event): void
    {
        $this->record($event->notifiable, $event->notification, $event->channel, DeliveryStatus::Sent);
    }

    public function handleFailed(NotificationFailed $event): void
    {
        $error = $event->data['exception'] ?? null;

        $this->record(
            $event->notifiable,
            $event->notification,
            $event->channel,
            DeliveryStatus::Failed,
            $error instanceof Throwable ? $error->getMessage() : 'Sending failed.',
        );
    }

    private function record(object $notifiable, object $notification, string $channel, DeliveryStatus $status, ?string $error = null): void
    {
        if ($channel !== NotificationDelivery::CHANNEL_MAIL || ! $notification instanceof AppNotification) {
            return;
        }

        NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'workspace_id' => $notification->workspaceId,
            'user_id' => $notifiable instanceof User ? $notifiable->getKey() : null,
            'event_key' => $notification->event->value,
            'channel' => NotificationDelivery::CHANNEL_MAIL,
            'target' => $this->maskedAddress($notifiable),
            'status' => $status,
            'attempts' => 1,
            'error' => $error !== null ? Str::limit($error, 480) : null,
            'sent_at' => $status === DeliveryStatus::Sent ? now() : null,
        ]);
    }

    private function maskedAddress(object $notifiable): ?string
    {
        $address = match (true) {
            $notifiable instanceof User => $notifiable->email,
            $notifiable instanceof AnonymousNotifiable => $notifiable->routeNotificationFor('mail'),
            default => null,
        };

        if (! is_string($address) || ! str_contains($address, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $address, 2);

        return Str::substr($local, 0, 1).'***@'.$domain;
    }
}
