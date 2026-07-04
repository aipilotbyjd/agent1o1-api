<?php

namespace App\Engine\Trigger\Normalizers;

use App\Contracts\PayloadNormalizer;

class DiscordNormalizer implements PayloadNormalizer
{
    public function provider(): string
    {
        return 'discord';
    }

    public function normalize(array $raw, string $providerEvent): array
    {
        return [
            'trigger' => ['provider' => 'discord', 'event' => $providerEvent],
            'data' => [
                'type' => $raw['type'] ?? null,
                'id' => $raw['id'] ?? null,
                'content' => $raw['content'] ?? null,
                'channel_id' => $raw['channel_id'] ?? null,
                'guild_id' => $raw['guild_id'] ?? null,
                'author' => isset($raw['author']) ? [
                    'id' => $raw['author']['id'] ?? null,
                    'username' => $raw['author']['username'] ?? null,
                    'bot' => $raw['author']['bot'] ?? false,
                ] : null,
                'emoji' => $raw['emoji'] ?? null,
                'member' => $raw['member'] ?? null,
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
