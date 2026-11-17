<?php

namespace App\Services\Notifications;

use App\Exceptions\Http\BlockedUrlException;
use App\Models\Notifications\NotificationChannel;
use App\Services\Http\GuardedHttp;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shapes a notification for one Slack, Discord or webhook channel and posts
 * it, through `GuardedHttp` so a stored URL can never reach an internal
 * address.
 *
 * Generic webhooks receive a versioned JSON document (`webhookPayload()`)
 * signed with the channel's secret:
 *
 *   X-Notification-Timestamp: <unix seconds>
 *   X-Notification-Signature: sha256=<hex HMAC-SHA256 of "<timestamp>.<raw body>">
 *
 * so a receiver can check both who sent it and that it isn't a replay.
 */
class IntegrationDeliverer
{
    public function __construct(private readonly GuardedHttp $http) {}

    /**
     * The body a channel of `$type` is sent for `$message`.
     *
     * @param  array{id: string, type: string, category: string, severity: string, workspace_id: string|null, data: array<string, mixed>}  $event
     * @return array<string, mixed>
     */
    public function payloadFor(string $type, IntegrationMessage $message, array $event): array
    {
        return match ($type) {
            'slack' => array_filter([
                'text' => $message->text(),
                'blocks' => $message->slackBlocks,
            ], fn (mixed $value): bool => $value !== null),
            'discord' => [
                'content' => Str::limit($message->text(), 1990),
                // Workspace content reaches this verbatim: never let it ping `@everyone`.
                'allowed_mentions' => ['parse' => []],
            ],
            default => $this->webhookPayload($message, $event),
        };
    }

    /**
     * @return array{ok: bool, message: string, retryable: bool}
     */
    public function deliverTest(NotificationChannel $channel): array
    {
        $message = new IntegrationMessage(title: 'Test notification', body: 'This is a test notification from your workspace.');

        return $this->deliver($channel, $this->payloadFor($channel->type, $message, [
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'category' => 'test',
            'severity' => 'info',
            'workspace_id' => $channel->workspace_id,
            'data' => [],
        ]));
    }

    /**
     * `retryable` says whether trying again could help: a network error or a
     * 5xx/429 might clear, a blocked address or a 4xx will not.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string, retryable: bool}
     */
    public function deliver(NotificationChannel $channel, array $payload): array
    {
        try {
            $config = $channel->config;
            $headers = $channel->type === 'webhook'
                ? [...($config['headers'] ?? []), ...$this->signatureHeaders($channel, $payload)]
                : [];

            $response = $this->post($config['url'], $payload, $headers);

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
     * Version 1 of the generic webhook document. `message` is the plain text
     * earlier versions sent on its own, kept for receivers built on it.
     *
     * @param  array{id: string, type: string, category: string, severity: string, workspace_id: string|null, data: array<string, mixed>}  $event
     * @return array<string, mixed>
     */
    private function webhookPayload(IntegrationMessage $message, array $event): array
    {
        return [
            'id' => $event['id'],
            'version' => (int) config('notifications.integrations.payload_version'),
            'type' => $event['type'],
            'category' => $event['category'],
            'severity' => $event['severity'],
            'workspace_id' => $event['workspace_id'],
            'title' => $message->title,
            'body' => $message->body,
            'message' => $message->text(),
            'action_url' => $message->actionUrl,
            'data' => $event['data'],
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Signs the exact JSON the HTTP client sends (`json_encode` with default
     * flags, as Guzzle encodes a `json` body).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function signatureHeaders(NotificationChannel $channel, array $payload): array
    {
        if (blank($channel->signing_secret)) {
            return [];
        }

        $timestamp = (string) now()->getTimestamp();
        $body = (string) json_encode($payload);

        return [
            (string) config('notifications.integrations.timestamp_header') => $timestamp,
            (string) config('notifications.integrations.signature_header') => 'sha256='.hash_hmac('sha256', "{$timestamp}.{$body}", (string) $channel->signing_secret),
        ];
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
