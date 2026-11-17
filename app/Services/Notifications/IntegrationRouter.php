<?php

namespace App\Services\Notifications;

use App\Enums\Notifications\DeliveryStatus;
use App\Jobs\Notifications\DeliverIntegrationJob;
use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationDelivery;
use App\Models\Notifications\NotificationRoute;
use App\Notifications\AppNotification;
use Illuminate\Support\Str;

/**
 * Sends a workspace notification to the chat and webhook channels its
 * routes cover. Runs once per event, not per recipient, so a channel gets
 * one message however many members were notified, and each channel gets at
 * most one message per route even when several routes match.
 *
 * Every message is recorded as a `NotificationDelivery` carrying the exact
 * payload, then sent by its own queued job so a slow or failing channel
 * holds nothing else up.
 */
class IntegrationRouter
{
    public function __construct(
        private readonly IntegrationDeliverer $deliverer,
        private readonly DeliveryGate $gate,
    ) {}

    /**
     * @return int How many deliveries were queued.
     */
    public function route(AppNotification $notification): int
    {
        if ($notification->workspaceId === null) {
            return 0;
        }

        $severity = $notification->severity();

        $channels = NotificationRoute::query()
            ->where('workspace_id', $notification->workspaceId)
            ->covering($notification->type)
            ->with('channel')
            ->get()
            ->filter(fn (NotificationRoute $route): bool => $route->allowsSeverity($severity)
                && $route->channel !== null
                && $route->channel->is_active)
            ->map(fn (NotificationRoute $route): NotificationChannel => $route->channel)
            ->unique('id')
            ->values();

        if ($channels->isEmpty()) {
            return 0;
        }

        $message = $notification->toIntegration();
        $event = [
            'id' => (string) Str::uuid(),
            'type' => $notification->type->value,
            'category' => $notification->type->definition()->category->value,
            'severity' => $severity->value,
            'workspace_id' => $notification->workspaceId,
            'data' => $notification->data,
        ];

        $queued = 0;

        foreach ($channels as $channel) {
            if (! $this->gate->allows($notification, "integration:{$channel->id}")) {
                continue;
            }

            $delivery = NotificationDelivery::query()->create([
                'workspace_id' => $notification->workspaceId,
                'notification_channel_id' => $channel->id,
                'event_key' => $notification->type->value,
                'channel' => $channel->type,
                'target' => $channel->name,
                'status' => DeliveryStatus::Pending,
                'payload' => $this->deliverer->payloadFor($channel->type, $message, $event),
            ]);

            DeliverIntegrationJob::dispatch($delivery->id)->afterCommit();
            $queued++;
        }

        return $queued;
    }
}
