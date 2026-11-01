<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class YouTubeUpdatePlaylistNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_update_playlist';
    }

    public function name(): string
    {
        return 'YouTube: Update Playlist';
    }

    public function description(): string
    {
        return "Changes a playlist's title, description or visibility. Anything left empty stays as it is.";
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['playlist_id'],
            'properties' => [
                ...$this->credentialFields(),
                'playlist_id' => Field::dynamic('Playlist', 'google_youtube.playlists', 'Pick one of your playlists, or map a playlist ID from an earlier step.', 'PLxxxxxxxxxxxxxxxx'),
                'title' => Field::text('New title'),
                'description' => Field::textarea('New description'),
                'privacy' => $this->privacyField(null, 'Leave empty to keep the current visibility.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = trim((string) $config['playlist_id']);

        $existing = $this->get($run, '/playlists', $config, ['part' => 'snippet,status', 'id' => $id])['items'][0] ?? null;

        if (! is_array($existing)) {
            throw new RuntimeException("YouTube: no playlist found with the ID \"{$id}\".");
        }

        $title = trim((string) ($config['title'] ?? ''));
        $description = trim((string) ($config['description'] ?? ''));
        $privacy = trim((string) ($config['privacy'] ?? ''));

        $response = $this->put($run, '/playlists?part=snippet,status', $config, [
            'id' => $id,
            'snippet' => [
                'title' => $title !== '' ? $title : ($existing['snippet']['title'] ?? ''),
                'description' => $description !== '' ? $description : ($existing['snippet']['description'] ?? ''),
            ],
            'status' => ['privacyStatus' => $privacy !== '' ? $privacy : ($existing['status']['privacyStatus'] ?? 'private')],
        ]);

        return $this->playlist($response);
    }
}
