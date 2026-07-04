<?php

namespace App\Engine\Webhook;

use App\Contracts\WebhookRegistrar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SlackRegistrar — manages Slack event subscriptions.
 *
 * Slack uses Event Subscriptions, not classic webhooks. The Request URL is set
 * in the Slack App settings (programmatically via apps.manifest.update when an
 * app-level token is available, otherwise manually).
 *
 * Slack signs every request with HMAC-SHA256 using a "Signing Secret" over
 * "v0:{timestamp}:{raw_body}". The signature is in X-Slack-Signature as
 * "v0=<hex>" and the timestamp in X-Slack-Request-Timestamp. Timestamps older
 * than 5 minutes are rejected to prevent replay attacks.
 *
 * The URL verification challenge (type=url_verification) is handled in
 * TriggerWebhookController before this registrar is involved.
 */
class SlackRegistrar implements WebhookRegistrar
{
    private const BASE_URL = 'https://slack.com/api';

    private const TIMESTAMP_TOLERANCE = 300;

    public function provider(): string
    {
        return 'slack';
    }

    public function supportsAutoRegistration(): bool
    {
        return true;
    }

    /**
     * Slack does not provide a "list webhooks" or "get webhook by ID" API.
     * We verify the app still has event subscriptions by calling auth.test.
     *
     * @param  array<string, mixed>  $credentials  Must contain 'bot_token' (xoxb-...).
     * @param  array<string, mixed>  $providerConfig
     */
    public function checkExists(string $externalId, array $credentials, array $providerConfig = []): bool
    {
        $token = $credentials['bot_token'] ?? $credentials['access_token'] ?? '';

        $response = Http::baseUrl(self::BASE_URL)
            ->withToken($token)
            ->get('/auth.test');

        return $response->successful() && ($response->json('ok') === true);
    }

    /**
     * Register a Slack event subscription URL via the manifest API.
     *
     * Requires an app-level token (xapp-...) in credentials['app_token'].
     * If not present, logs a warning and returns a placeholder — the user
     * must manually set the Request URL in the Slack App dashboard.
     *
     * @param  list<string>  $events  Slack event types (e.g. ['message', 'app_mention']).
     * @param  array<string, mixed>  $credentials  Must contain 'signing_secret' and optionally 'app_token'.
     * @param  array<string, mixed>  $providerConfig
     * @return array{external_id: string, secret: string}
     */
    public function register(string $callbackUrl, array $events, array $credentials, array $providerConfig = []): array
    {
        $signingSecret = $credentials['signing_secret'] ?? '';

        if (empty($signingSecret)) {
            throw new \RuntimeException(
                'Slack registration requires a signing_secret in the credential. '.
                'Find it in your Slack App settings under "Basic Information > App Credentials".'
            );
        }

        $appToken = $credentials['app_token'] ?? null;

        if (! $appToken) {
            Log::warning('SlackRegistrar: no app_token provided. Slack event subscription URL must be set manually.', [
                'callback_url' => $callbackUrl,
            ]);

            return [
                'external_id' => 'manual-'.($providerConfig['app_id'] ?? 'unknown'),
                'secret' => $signingSecret,
            ];
        }

        $appId = $providerConfig['app_id'] ?? null;

        if (! $appId) {
            throw new \RuntimeException('Slack registration requires app_id in provider_config.');
        }

        $response = Http::baseUrl(self::BASE_URL)
            ->withToken($appToken)
            ->post('/apps.manifest.update', [
                'app_id' => $appId,
                'manifest' => json_encode([
                    'event_subscriptions' => [
                        'request_url' => $callbackUrl,
                        'bot_events' => $events,
                    ],
                ]),
            ]);

        if (! $response->successful() || ! $response->json('ok')) {
            throw new \RuntimeException('Slack manifest update failed: '.($response->json('error') ?? 'unknown error'));
        }

        return [
            'external_id' => $appId,
            'secret' => $signingSecret,
        ];
    }

    /**
     * Slack does not support deleting event subscriptions via API.
     * The URL must be cleared manually in the Slack App dashboard.
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     */
    public function unregister(string $externalId, array $credentials, array $providerConfig = []): void
    {
        Log::info('SlackRegistrar: Slack event subscriptions cannot be removed via API. Please clear the Request URL in your Slack App dashboard.', [
            'external_id' => $externalId,
        ]);
    }

    /**
     * Verify the X-Slack-Signature header.
     *
     * Slack signs requests as: HMAC-SHA256("v0:{timestamp}:{body}", signing_secret)
     * compared to X-Slack-Signature: "v0={hex_hash}".
     *
     * The $signature parameter is "{timestamp}|{X-Slack-Signature header value}",
     * a convention set by TriggerWebhookController.
     *
     * @param  string  $payload  Raw request body.
     * @param  string  $signature  "{timestamp}|{X-Slack-Signature header value}"
     * @param  string  $secret  The Slack signing secret.
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        [$timestamp, $sigHeader] = explode('|', $signature, 2) + ['', ''];

        if (empty($timestamp) || empty($sigHeader)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            return false;
        }

        $baseString = "v0:{$timestamp}:{$payload}";
        $computed = 'v0='.hash_hmac('sha256', $baseString, $secret);

        return hash_equals($computed, $sigHeader);
    }
}
