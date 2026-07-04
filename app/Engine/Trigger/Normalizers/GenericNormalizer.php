<?php

namespace App\Engine\Trigger\Normalizers;

use App\Contracts\PayloadNormalizer;

class GenericNormalizer implements PayloadNormalizer
{
    public function provider(): string
    {
        return 'generic';
    }

    public function normalize(array $raw, string $providerEvent): array
    {
        return [
            'trigger' => [
                'provider' => 'generic',
                'event' => $providerEvent ?: 'trigger',
            ],
            'data' => $raw,
            'meta' => [
                'received_at' => now()->toIso8601String(),
                'delivery_id' => null,
            ],
        ];
    }

    public function extractDedupKey(array $raw, array $headers = []): ?string
    {
        return null;
    }
}
