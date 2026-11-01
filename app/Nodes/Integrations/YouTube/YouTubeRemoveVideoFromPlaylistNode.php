<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class YouTubeRemoveVideoFromPlaylistNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_remove_video_from_playlist';
    }

    public function name(): string
    {
        return 'YouTube: Remove Video from Playlist';
    }

    public function description(): string
    {
        return 'Takes a video out of one of your playlists. The video itself is not deleted.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['playlist_id', 'video_id'],
            'properties' => [
                ...$this->credentialFields(),
                'playlist_id' => Field::dynamic('Playlist', 'google_youtube.playlists', 'Pick one of your playlists, or map a playlist ID from an earlier step.', 'PLxxxxxxxxxxxxxxxx'),
                'video_id' => Field::text('Video ID or link', 'The ID from the video\'s link (watch?v=...), or the whole link.', 'dQw4w9WgXcQ'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $videoId = $this->videoIdFromLink((string) $config['video_id']);

        $items = (array) ($this->get($run, '/playlistItems', $config, [
            'part' => 'id',
            'playlistId' => $config['playlist_id'],
            'videoId' => $videoId,
            'maxResults' => 50,
        ])['items'] ?? []);

        if ($items === []) {
            throw new RuntimeException("YouTube: the video \"{$videoId}\" is not in that playlist.");
        }

        foreach ($items as $item) {
            $this->delete($run, '/playlistItems?'.http_build_query(['id' => $item['id']]), $config);
        }

        return ['removed' => count($items), 'playlist_id' => $config['playlist_id'], 'video_id' => $videoId];
    }
}
