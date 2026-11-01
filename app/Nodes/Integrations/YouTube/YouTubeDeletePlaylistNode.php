<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class YouTubeDeletePlaylistNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_delete_playlist';
    }

    public function name(): string
    {
        return 'YouTube: Delete Playlist';
    }

    public function description(): string
    {
        return 'Deletes a playlist. The videos in it are not deleted.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['playlist_id'],
            'properties' => [
                ...$this->credentialFields(),
                'playlist_id' => Field::dynamic('Playlist', 'google_youtube.playlists', 'Pick one of your playlists, or map a playlist ID from an earlier step.', 'PLxxxxxxxxxxxxxxxx'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->delete($run, '/playlists?'.http_build_query(['id' => $config['playlist_id']]), $config);

        return ['deleted' => true, 'playlist_id' => $config['playlist_id']];
    }
}
