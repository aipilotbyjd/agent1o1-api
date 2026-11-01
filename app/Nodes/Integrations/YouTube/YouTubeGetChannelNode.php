<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class YouTubeGetChannelNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_get_channel';
    }

    public function name(): string
    {
        return 'YouTube: Get Channel';
    }

    public function description(): string
    {
        return 'Reads a channel\'s details and subscriber, video and view counts. Leave it empty for your own channel.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::text('Channel ID or @handle', 'Leave empty to use the connected account\'s own channel.', '@YouTubeCreators'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = trim((string) ($config['channel'] ?? ''));

        $response = $this->get($run, '/channels', $config, [
            'part' => 'snippet,statistics,contentDetails',
            'mine' => $channel === '' ? 'true' : null,
            'forHandle' => str_starts_with($channel, '@') ? $channel : null,
            'id' => $channel !== '' && ! str_starts_with($channel, '@') ? $channel : null,
        ]);

        $item = $response['items'][0] ?? null;

        if (! is_array($item)) {
            throw new RuntimeException('YouTube: no channel found.');
        }

        return array_filter([
            'channel_id' => $item['id'] ?? null,
            'title' => $item['snippet']['title'] ?? null,
            'description' => $item['snippet']['description'] ?? null,
            'handle' => $item['snippet']['customUrl'] ?? null,
            'created_at' => $item['snippet']['publishedAt'] ?? null,
            'thumbnail' => $item['snippet']['thumbnails']['high']['url'] ?? null,
            'subscribers' => isset($item['statistics']['subscriberCount']) ? (int) $item['statistics']['subscriberCount'] : null,
            'videos' => isset($item['statistics']['videoCount']) ? (int) $item['statistics']['videoCount'] : null,
            'views' => isset($item['statistics']['viewCount']) ? (int) $item['statistics']['viewCount'] : null,
            'uploads_playlist_id' => $item['contentDetails']['relatedPlaylists']['uploads'] ?? null,
            'url' => isset($item['id']) ? 'https://www.youtube.com/channel/'.$item['id'] : null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
