<?php

namespace App\Notifications\Channels;

use App\Enums\Notifications\AlertSeverity;
use App\Notifications\Admin\AdminAlertNotification;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers admin alerts to the operator destinations configured in
 * `config/admin_alerts.php`. Separate from `WorkspaceWebhookChannel`, which
 * posts to workspace-owned `NotificationChannel` rows on behalf of tenants:
 * this one only ever reads environment configuration.
 *
 * Delivery failures are logged, never thrown: an alert that cannot reach
 * Discord must not break the billing path that raised it.
 */
class AdminAlertChannel
{
    /**
     * Discord rejects embeds with more than 25 fields outright, so long
     * contexts are truncated rather than losing the whole alert.
     */
    private const int MAX_DISCORD_FIELDS = 25;

    private const int MAX_DISCORD_FIELD_LENGTH = 1024;

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof AdminAlertNotification) {
            return;
        }

        $payload = $notification->toAdminAlert($notifiable);

        foreach ($payload['destinations'] as $destination) {
            $this->deliver($destination['type'], $destination['url'], $payload);
        }
    }

    /**
     * @param  array{key: string, severity: string, title: string, body: string|null, context: array<string, mixed>, destinations: array<int, array{type: string, url: string}>}  $payload
     */
    private function deliver(string $type, string $url, array $payload): void
    {
        try {
            $request = Http::timeout(10)->connectTimeout(3)->retry(2, 250);

            $response = match ($type) {
                'discord' => $request->post($url, $this->discordPayload($payload)),
                'webhook' => $this->postWebhook($request, $url, $payload),
                default => null,
            };

            if ($response !== null && ! $response->successful()) {
                Log::warning('Admin alert delivery failed.', [
                    'destination' => $type,
                    'alert' => $payload['key'],
                    'status' => $response->status(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Admin alert delivery errored.', [
                'destination' => $type,
                'alert' => $payload['key'],
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Signed over the exact bytes sent, so the receiver can verify the body it
     * reads rather than a re-encoding of it.
     *
     * @param  array{key: string, severity: string, title: string, body: string|null, context: array<string, mixed>, destinations: array<int, array{type: string, url: string}>}  $payload
     */
    private function postWebhook(PendingRequest $request, string $url, array $payload): Response
    {
        $body = json_encode([
            'key' => $payload['key'],
            'severity' => $payload['severity'],
            'title' => $payload['title'],
            'body' => $payload['body'],
            'context' => (object) $payload['context'],
            'environment' => config('app.env'),
            'occurred_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        $secret = config('admin_alerts.webhook_secret');

        $headers = [];

        if (filled($secret)) {
            $headers['X-Admin-Alert-Signature'] = 'sha256='.hash_hmac('sha256', $body, (string) $secret);
        }

        return $request->withHeaders($headers)->withBody($body, 'application/json')->post($url);
    }

    /**
     * @param  array{key: string, severity: string, title: string, body: string|null, context: array<string, mixed>, destinations: array<int, array{type: string, url: string}>}  $payload
     * @return array<string, mixed>
     */
    private function discordPayload(array $payload): array
    {
        $embed = [
            'title' => mb_substr($payload['title'], 0, 256),
            'color' => $this->colorFor($payload['severity']),
            'fields' => $this->discordFields($payload['context']),
            'footer' => ['text' => $payload['key'].' · '.config('app.env')],
            'timestamp' => now()->toIso8601String(),
        ];

        if (filled($payload['body'])) {
            $embed['description'] = mb_substr((string) $payload['body'], 0, 4096);
        }

        // Workspace names reach this payload verbatim, so mention parsing is
        // disabled outright: a tenant naming their workspace `@everyone`
        // must not be able to ping the operator channel.
        return [
            'embeds' => [$embed],
            'allowed_mentions' => ['parse' => []],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array{name: string, value: string, inline: bool}>
     */
    private function discordFields(array $context): array
    {
        $fields = [];

        foreach (array_slice($context, 0, self::MAX_DISCORD_FIELDS, preserve_keys: true) as $name => $value) {
            $rendered = AdminAlertNotification::stringify($value);

            $fields[] = [
                'name' => mb_substr(AdminAlertNotification::describe((string) $name), 0, 256),
                'value' => $rendered === '' ? '-' : mb_substr($rendered, 0, self::MAX_DISCORD_FIELD_LENGTH),
                'inline' => true,
            ];
        }

        return $fields;
    }

    private function colorFor(string $severity): int
    {
        return AlertSeverity::from($severity)->color();
    }
}
