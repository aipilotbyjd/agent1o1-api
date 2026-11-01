<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class YouTubeGetVideoNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_get_video';
    }

    public function name(): string
    {
        return 'YouTube: Get Video';
    }

    public function description(): string
    {
        return 'Reads a video\'s title, length and view, like and comment counts.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['video_id'],
            'properties' => [
                ...$this->credentialFields(),
                'video_id' => Field::text('Video ID or link', 'The ID from the video\'s link (watch?v=...), or the whole link.', 'dQw4w9WgXcQ'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->videoIdFromLink((string) $config['video_id']);

        $response = $this->get($run, '/videos', $config, ['part' => 'snippet,contentDetails,statistics', 'id' => $id]);
        $video = $response['items'][0] ?? null;

        if (! is_array($video)) {
            throw new RuntimeException("YouTube: no video found with the ID \"{$id}\".");
        }

        return $this->video($video);
    }
}
