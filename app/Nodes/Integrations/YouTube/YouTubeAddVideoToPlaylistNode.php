<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class YouTubeAddVideoToPlaylistNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_add_video_to_playlist';
    }

    public function name(): string
    {
        return 'YouTube: Add Video to Playlist';
    }

    public function description(): string
    {
        return 'Adds a video to one of your playlists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
                'position' => Field::integer('Position', 'Where in the playlist, starting at 0. Leave empty to add it at the end.', null, 0),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $videoId = $this->videoIdFromLink((string) $config['video_id']);
        $position = $config['position'] ?? null;

        $response = $this->post($run, '/playlistItems?part=snippet', $config, [
            'snippet' => array_filter([
                'playlistId' => $config['playlist_id'],
                'resourceId' => ['kind' => 'youtube#video', 'videoId' => $videoId],
                'position' => $position === null || $position === '' ? null : (int) $position,
            ], fn (mixed $value): bool => $value !== null),
        ]);

        return [
            'playlist_item_id' => $response['id'] ?? null,
            'playlist_id' => $config['playlist_id'],
            'video_id' => $videoId,
            'position' => $response['snippet']['position'] ?? null,
        ];
    }
}
