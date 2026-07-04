<?php

namespace App\Contracts;

interface PayloadNormalizer
{
    /**
     * The provider slug this normalizer handles (e.g. 'github', 'slack').
     */
    public function provider(): string;

    /**
     * Normalize a raw provider payload into a consistent shape.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalize(array $raw, string $providerEvent): array;

    /**
     * Extract a stable deduplication key from the raw payload/headers, or null
     * when the provider offers no reliable idempotency key.
     *
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $headers
     */
    public function extractDedupKey(array $raw, array $headers = []): ?string;
}
