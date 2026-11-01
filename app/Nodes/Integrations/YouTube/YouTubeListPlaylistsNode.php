<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class YouTubeListPlaylistsNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_list_playlists';
    }

    public function name(): string
    {
        return 'YouTube: List Playlists';
    }

    public function description(): string
    {
        return 'Lists your playlists, or the public playlists of any channel.';
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
                'channel_id' => Field::text('Channel ID', 'A channel ID such as UCxxxxxxxxxxxxxxxxxxxxxx. Leave empty for the connected account\'s own playlists.'),
                'max_results' => Field::integer('Max results', null, 25, 1, 50),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channelId = trim((string) ($config['channel_id'] ?? ''));

        $response = $this->get($run, '/playlists', $config, [
            'part' => 'snippet,contentDetails,status',
            'mine' => $channelId === '' ? 'true' : null,
            'channelId' => $channelId !== '' ? $channelId : null,
            'maxResults' => $config['max_results'] ?? 25,
        ]);

        return ['playlists' => array_map(fn (array $item): array => $this->playlist($item), (array) ($response['items'] ?? []))];
    }
}
