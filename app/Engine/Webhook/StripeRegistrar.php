<?php

namespace App\Engine\Webhook;

use App\Contracts\WebhookRegistrar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Stripe webhook registrar.
 *
 * Manages webhook endpoints via the Stripe API and verifies
 * incoming webhook signatures using Stripe's v1 HMAC scheme.
 */
class StripeRegistrar implements WebhookRegistrar
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    private const TIMESTAMP_TOLERANCE = 300;

    public function provider(): string
    {
        return 'stripe';
    }

    public function supportsAutoRegistration(): bool
    {
        return true;
    }

    /**
     * Check if the webhook endpoint still exists on Stripe.
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     */
    public function checkExists(string $externalId, array $credentials, array $providerConfig = []): bool
    {
        $response = $this->client($credentials)
            ->get("/webhook_endpoints/{$externalId}");

        return $response->status() === 200;
    }

    /**
     * Register a new webhook endpoint with Stripe.
     *
     * @param  list<string>  $events
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     * @return array{external_id: string, secret: string}
     *
     * @throws RequestException
     */
    public function register(string $callbackUrl, array $events, array $credentials, array $providerConfig = []): array
    {
        $response = $this->client($credentials)
            ->asForm()
            ->post('/webhook_endpoints', [
                'url' => $callbackUrl,
                'enabled_events' => $events,
                'description' => 'Created by Agent1o1',
            ]);

        $response->throw();

        return [
            'external_id' => (string) $response->json('id'),
            'secret' => (string) $response->json('secret'),
        ];
    }

    /**
     * Unregister (delete) a webhook endpoint from Stripe.
     *
     * Silently ignores 404 errors when the webhook has already been removed.
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     *
     * @throws RequestException
     */
    public function unregister(string $externalId, array $credentials, array $providerConfig = []): void
    {
        $response = $this->client($credentials)
            ->delete("/webhook_endpoints/{$externalId}");

        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    /**
     * Verify the signature of an incoming Stripe webhook payload.
     *
     * Parses the `Stripe-Signature` header (t=...,v1=...) and validates
     * the HMAC-SHA256 hash with a 300-second timestamp tolerance.
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $parts = collect(explode(',', $signature))
            ->mapWithKeys(function (string $part): array {
                $segments = explode('=', $part, 2);

                if (count($segments) !== 2) {
                    return [];
                }

                return [trim($segments[0]) => trim($segments[1])];
            });

        $timestamp = $parts->get('t');
        $expectedHash = $parts->get('v1');

        if ($timestamp === null || $expectedHash === null) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            return false;
        }

        $computedHash = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return hash_equals($expectedHash, $computedHash);
    }

    /**
     * Build an authenticated HTTP client for the Stripe API.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function client(array $credentials): PendingRequest
    {
        $token = $credentials['secret_key'] ?? $credentials['api_key'] ?? '';

        return Http::baseUrl(self::BASE_URL)
            ->withToken($token)
            ->timeout(30);
    }
}
