<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class YouTubeDeleteVideoNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_delete_video';
    }

    public function name(): string
    {
        return 'YouTube: Delete Video';
    }

    public function description(): string
    {
        return "Permanently deletes a video from the connected account's channel.";
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['video_id'],
            'properties' => [
                ...$this->credentialFields(),
                'video_id' => Field::text('Video ID or link', 'A video on the connected account\'s channel.', 'dQw4w9WgXcQ'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $videoId = $this->videoIdFromLink((string) $config['video_id']);

        $this->delete($run, '/videos?'.http_build_query(['id' => $videoId]), $config);

        return ['deleted' => true, 'video_id' => $videoId];
    }
}
