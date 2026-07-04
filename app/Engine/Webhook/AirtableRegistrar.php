<?php

namespace App\Engine\Webhook;

use App\Contracts\WebhookRegistrar;
use Illuminate\Support\Facades\Http;

class AirtableRegistrar implements WebhookRegistrar
{
    public function provider(): string
    {
        return 'airtable';
    }

    public function supportsAutoRegistration(): bool
    {
        return true;
    }

    /**
     * @param  list<string>  $events
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     * @return array{external_id: string, secret: string}
     */
    public function register(string $callbackUrl, array $events, array $credentials, array $providerConfig = []): array
    {
        $baseId = $providerConfig['base_id'] ?? null;
        if (! $baseId) {
            throw new \RuntimeException('Airtable base_id is required for webhook registration');
        }

        $token = $credentials['api_key'] ?? $credentials['token'] ?? null;
        if (! $token) {
            throw new \RuntimeException('Airtable API token is required');
        }

        $response = Http::withToken($token)
            ->post("https://api.airtable.com/v0/bases/{$baseId}/webhooks", [
                'notificationUrl' => $callbackUrl,
                'specification' => [
                    'options' => [
                        'filters' => [
                            'fromSources' => ['client'],
                            'dataTypes' => ['tableData'],
                            'changeTypes' => ['add', 'update', 'remove'],
                        ],
                    ],
                ],
            ]);

        $response->throw();

        $data = $response->json();

        return [
            'external_id' => $data['id'],
            'secret' => $data['macSecretBase64'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     */
    public function unregister(string $externalId, array $credentials, array $providerConfig = []): void
    {
        $baseId = $providerConfig['base_id'] ?? null;
        $token = $credentials['api_key'] ?? $credentials['token'] ?? null;

        if (! $baseId || ! $token || ! $externalId) {
            return;
        }

        Http::withToken($token)
            ->delete("https://api.airtable.com/v0/bases/{$baseId}/webhooks/{$externalId}");
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     */
    public function checkExists(string $externalId, array $credentials, array $providerConfig = []): bool
    {
        $baseId = $providerConfig['base_id'] ?? null;
        $token = $credentials['api_key'] ?? $credentials['token'] ?? null;

        if (! $baseId || ! $token) {
            return false;
        }

        $response = Http::withToken($token)
            ->get("https://api.airtable.com/v0/bases/{$baseId}/webhooks");

        if (! $response->ok()) {
            return false;
        }

        $webhooks = $response->json()['webhooks'] ?? [];

        return collect($webhooks)->contains('id', $externalId);
    }

    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        if (! $secret || ! $signature) {
            return false;
        }

        $decoded = base64_decode($secret);
        $expected = hash_hmac('sha256', $payload, $decoded);

        return hash_equals($expected, ltrim($signature, 'hmac-sha256='));
    }
}
