<?php

namespace App\Jobs\Notifications;

use App\Enums\Queue;
use App\Models\Notifications\NotificationChannel;
use App\Notifications\Channels\WorkspaceWebhookChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

/**
 * Delivers one notification to one chat/webhook endpoint. One job per
 * endpoint, so a failing endpoint is retried with backoff on its own: it
 * can't re-send the mail or in-app copies, or hold up the other endpoints.
 * Permanent failures (a blocked address, a 4xx) are logged and dropped
 * rather than retried.
 */
class DeliverWorkspaceWebhookJob implements ShouldQueue
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

    /**
     * @param  array<int, mixed>|null  $slackBlocks
     */
    public function __construct(
        public int|string $channelId,
        public string $message,
        public ?array $slackBlocks = null,
    ) {
        $this->onQueue(Queue::Notification->value);
    }

    public function handle(WorkspaceWebhookChannel $webhooks): void
    {
        $channel = NotificationChannel::query()->whereKey($this->channelId)->where('is_active', true)->first();

        if ($channel === null) {
            return;
        }

        $result = $webhooks->deliver($channel, $this->message, $this->slackBlocks);

        if (! $result['ok'] && $result['retryable']) {
            throw new RuntimeException($result['message']);
        }
    }
}
