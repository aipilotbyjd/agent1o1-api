<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class YouTubeCreatePlaylistNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_create_playlist';
    }

    public function name(): string
    {
        return 'YouTube: Create Playlist';
    }

    public function description(): string
    {
        return "Creates a playlist on the connected account's channel.";
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title', null, 'Launch videos'),
                'description' => Field::textarea('Description'),
                'privacy' => $this->privacyField('private'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->post($run, '/playlists?part=snippet,status', $config, [
            'snippet' => array_filter([
                'title' => $config['title'],
                'description' => trim((string) ($config['description'] ?? '')) ?: null,
            ]),
            'status' => ['privacyStatus' => $config['privacy'] ?? 'private'],
        ]);

        return $this->playlist($response);
    }
}
