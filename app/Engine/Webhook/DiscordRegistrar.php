<?php

namespace App\Engine\Webhook;

use App\Contracts\WebhookRegistrar;
use Illuminate\Support\Facades\Log;

/**
 * DiscordRegistrar — handles Discord Interactions endpoint verification.
 *
 * Discord does NOT use traditional webhooks. It uses an "Interactions Endpoint
 * URL" set manually in the Discord Developer Portal — there is no API to set it
 * programmatically, so supportsAutoRegistration() returns false.
 *
 * Discord signs requests with Ed25519 (not HMAC). Every request carries:
 *   X-Signature-Ed25519   — hex-encoded Ed25519 signature
 *   X-Signature-Timestamp — Unix timestamp string
 * The signed message is timestamp + raw_body, verified against the application's
 * PUBLIC key. Requires the PHP sodium extension.
 *
 * The PING verification (type=1) must be handled synchronously — that happens in
 * TriggerWebhookController before dispatching to the queue.
 */
class DiscordRegistrar implements WebhookRegistrar
{
    public function provider(): string
    {
        return 'discord';
    }

    /**
     * Discord does not support API-based webhook URL registration.
     * The user must set the Interactions Endpoint URL manually in the portal.
     */
    public function supportsAutoRegistration(): bool
    {
        return false;
    }

    /**
     * Discord doesn't expose a "check webhook exists" API.
     * We verify the application credentials are valid by checking
     * that a public_key is stored — we can't make an API call without auth.
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     */
    public function checkExists(string $externalId, array $credentials, array $providerConfig = []): bool
    {
        return ! empty($credentials['public_key']);
    }

    /**
     * Discord requires manual setup — register() is a no-op stub.
     *
     * @param  list<string>  $events
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     * @return array{external_id: string, secret: string}
     */
    public function register(string $callbackUrl, array $events, array $credentials, array $providerConfig = []): array
    {
        return [
            'external_id' => 'manual',
            'secret' => $credentials['public_key'] ?? '',
        ];
    }

    /**
     * Discord requires manual setup — unregister() is a no-op stub.
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     */
    public function unregister(string $externalId, array $credentials, array $providerConfig = []): void
    {
        // No-op: Discord webhook URLs must be cleared manually in the Developer Portal.
    }

    /**
     * Verify Discord's Ed25519 signature.
     *
     * The $signature parameter is "{timestamp}|{X-Signature-Ed25519 header value}",
     * a convention set by TriggerWebhookController. The $secret parameter is the
     * Discord application public key (hex-encoded).
     *
     * @param  string  $payload  Raw request body.
     * @param  string  $signature  "{timestamp}|{hex-encoded Ed25519 signature}"
     * @param  string  $secret  Discord application public key (hex-encoded).
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        if (! extension_loaded('sodium')) {
            Log::error('DiscordRegistrar: sodium PHP extension is required for Discord signature verification.');

            return false;
        }

        [$timestamp, $sigHex] = explode('|', $signature, 2) + ['', ''];

        if (empty($timestamp) || empty($sigHex)) {
            return false;
        }

        try {
            $publicKey = sodium_hex2bin($secret);
            $sigBin = sodium_hex2bin($sigHex);
            $message = $timestamp.$payload;

            return sodium_crypto_sign_verify_detached($sigBin, $message, $publicKey);
        } catch (\SodiumException $e) {
            Log::warning('DiscordRegistrar: signature verification threw SodiumException', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
