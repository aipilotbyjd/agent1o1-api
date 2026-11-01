<?php

namespace App\Notifications\Channels;

use App\Exceptions\Http\BlockedUrlException;
use App\Jobs\Notifications\DeliverWorkspaceWebhookJob;
use App\Models\Notifications\NotificationChannel;
use App\Services\Http\GuardedHttp;
use Illuminate\Http\Client\Response;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkspaceWebhookChannel
{
    public function __construct(private readonly GuardedHttp $http) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWorkspaceChannel')) {
            return;
        }

        $payload = $notification->toWorkspaceChannel($notifiable);
        $channelIds = $payload['channel_ids'];

        if ($channelIds === []) {
            return;
        }

        NotificationChannel::query()
            ->where('workspace_id', $payload['workspace_id'])
            ->whereIn('id', $channelIds)
            ->where('is_active', true)
            ->each(fn (NotificationChannel $channel) => DeliverWorkspaceWebhookJob::dispatch($channel->id, $payload['message'], $payload['slack_blocks'] ?? null));
    }

    /**
     * @return array{ok: bool, message: string, retryable: bool}
     */
    public function deliverTest(NotificationChannel $channel): array
    {
        return $this->deliver($channel, 'This is a test notification from your workspace.');
    }

    /**
     * `$slackBlocks` is interactive content (e.g. approve/reject buttons)
     * for a Slack channel; `$message` stays the text fallback Slack shows
     * in notifications.
     *
     * `retryable` says whether trying again could help: a network error or
     * a 5xx/429 might clear, a blocked address or a 4xx will not.
     *
     * @param  array<int, mixed>|null  $slackBlocks
     * @return array{ok: bool, message: string, retryable: bool}
     */
    public function deliver(NotificationChannel $channel, string $message, ?array $slackBlocks = null): array
    {
        try {
            $config = $channel->config;

            $response = match ($channel->type) {
                'discord' => $this->post($config['url'], ['content' => $message]),
                'slack' => $this->post($config['url'], array_filter(['text' => $message, 'blocks' => $slackBlocks])),
                'webhook' => $this->post($config['url'], ['message' => $message], $config['headers'] ?? []),
            };

            if ($response->successful()) {
                return ['ok' => true, 'message' => 'Delivered.', 'retryable' => false];
            }

            Log::warning('Workspace notification channel delivery failed.', [
                'channel_id' => $channel->id,
                'status' => $response->status(),
            ]);

            return [
                'ok' => false,
                'message' => "Delivery failed: HTTP {$response->status()}.",
                'retryable' => $response->serverError() || in_array($response->status(), [408, 429], true),
            ];
        } catch (BlockedUrlException $exception) {
            Log::warning('Workspace notification channel delivery blocked.', [
                'channel_id' => $channel->id,
                'exception' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'message' => $exception->getMessage(), 'retryable' => false];
        } catch (Throwable $exception) {
            Log::warning('Workspace notification channel delivery errored.', [
                'channel_id' => $channel->id,
                'exception' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Delivery error.', 'retryable' => true];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    private function post(string $url, array $payload, array $headers = []): Response
    {
        return $this->http->send('POST', $url, $headers, $payload, timeoutSeconds: 10, retries: 2);
    }
}
