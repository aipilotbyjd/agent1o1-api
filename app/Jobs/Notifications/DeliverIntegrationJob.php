<?php

namespace App\Jobs\Notifications;

use App\Enums\Notifications\DeliveryStatus;
use App\Enums\Queue;
use App\Models\Notifications\NotificationDelivery;
use App\Notifications\Workspace\IntegrationChannelDisabledNotification;
use App\Services\Notifications\IntegrationDeliverer;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;
use Throwable;

/**
 * Sends one recorded delivery to one chat/webhook channel. One job per
 * channel, so a failing channel is retried with backoff on its own: it
 * can't re-send the email or inbox copies, or hold up other channels.
 *
 * A failure that retrying can't fix (a blocked address, a 4xx), or the last
 * retry, counts against the channel; enough of those in a row switch it off
 * and tell the workspace's admins.
 */
class DeliverIntegrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 4;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(public string $deliveryId)
    {
        $this->onQueue(Queue::Notification->value);
    }

    public function handle(IntegrationDeliverer $deliverer, NotificationDispatcher $notifications): void
    {
        $delivery = NotificationDelivery::query()->with('notificationChannel')->find($this->deliveryId);

        if ($delivery === null || $delivery->status !== DeliveryStatus::Pending) {
            return;
        }

        $channel = $delivery->notificationChannel;

        if ($channel === null || ! $channel->is_active) {
            $delivery->forceFill(['status' => DeliveryStatus::Skipped, 'error' => 'The channel was deleted or switched off.'])->save();

            return;
        }

        $result = $deliverer->deliver($channel, $delivery->payload ?? []);

        if ($result['ok']) {
            $delivery->markSent();
            $channel->recordDelivered();

            return;
        }

        $final = ! $result['retryable'] || $this->attempts() >= $this->tries;
        $delivery->markFailed($result['message'], $final);

        if (! $final) {
            throw new RuntimeException($result['message']);
        }

        if ($channel->recordFailure($result['message'])) {
            $notifications->dispatch(
                $notifications->ownersAndAdmins($channel->workspace),
                new IntegrationChannelDisabledNotification($channel),
            );
        }
    }

    /**
     * A job that died without finishing (a timeout, a crash) still counts.
     */
    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::query()
            ->whereKey($this->deliveryId)
            ->where('status', DeliveryStatus::Pending)
            ->update(['status' => DeliveryStatus::Failed, 'error' => 'Delivery did not complete.', 'updated_at' => now()]);
    }
}
