<?php

namespace App\Engine\Trigger\Normalizers;

use App\Contracts\PayloadNormalizer;

class StripeNormalizer implements PayloadNormalizer
{
    public function provider(): string
    {
        return 'stripe';
    }

    public function normalize(array $raw, string $providerEvent): array
    {
        $object = $raw['data']['object'] ?? [];

        return [
            'trigger' => ['provider' => 'stripe', 'event' => $providerEvent],
            'data' => [
                'event_type' => $raw['type'] ?? $providerEvent,
                'event_id' => $raw['id'] ?? null,
                'livemode' => $raw['livemode'] ?? false,
                'object_type' => $object['object'] ?? null,
                'object_id' => $object['id'] ?? null,
                'object' => $object,
            ],
            'meta' => [
                'received_at' => now()->toIso8601String(),
                'delivery_id' => $raw['id'] ?? null,
            ],
        ];
    }

    public function extractDedupKey(array $raw, array $headers = []): ?string
    {
        return $raw['id'] ?? null;
    }
}
