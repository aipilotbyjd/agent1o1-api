<?php

namespace App\Contracts;

interface WebhookRegistrar
{
    public function provider(): string;

    public function supportsAutoRegistration(): bool;

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     */
    public function checkExists(string $externalId, array $credentials, array $providerConfig = []): bool;

    public function verifySignature(string $payload, string $signature, string $secret): bool;

    /**
     * Register a webhook with the provider.
     * Only called when supportsAutoRegistration() returns true.
     *
     * @param  list<string>  $events
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     * @return array{external_id: string, secret: string}
     */
    public function register(string $callbackUrl, array $events, array $credentials, array $providerConfig = []): array;

    /**
     * Unregister a webhook from the provider. Must be idempotent.
     * Only called when supportsAutoRegistration() returns true.
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     */
    public function unregister(string $externalId, array $credentials, array $providerConfig = []): void;
}
