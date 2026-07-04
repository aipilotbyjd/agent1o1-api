<?php

namespace App\Engine\Trigger\Normalizers;

use App\Contracts\PayloadNormalizer;

class SlackNormalizer implements PayloadNormalizer
{
    public function provider(): string
    {
        return 'slack';
    }

    public function normalize(array $raw, string $providerEvent): array
    {
        $event = $raw['event'] ?? $raw;

        $data = [
            'type' => $event['type'] ?? $providerEvent,
            'text' => $event['text'] ?? null,
            'user' => $event['user'] ?? null,
            'channel' => $event['channel'] ?? null,
            'ts' => $event['ts'] ?? null,
            'thread_ts' => $event['thread_ts'] ?? null,
            'team' => $raw['team_id'] ?? null,
            'reaction' => $event['reaction'] ?? null,
            'item' => $event['item'] ?? null,
        ];

        return [
            'trigger' => ['provider' => 'slack', 'event' => $providerEvent],
            'data' => array_filter($data, fn ($v) => $v !== null),
            'meta' => [
                'received_at' => now()->toIso8601String(),
                'delivery_id' => $raw['event_id'] ?? null,
            ],
        ];
    }

    public function extractDedupKey(array $raw, array $headers = []): ?string
    {
        return $raw['event_id'] ?? null;
    }
}
