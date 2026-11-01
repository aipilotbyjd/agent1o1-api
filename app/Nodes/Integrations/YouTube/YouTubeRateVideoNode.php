<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class YouTubeRateVideoNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_rate_video';
    }

    public function name(): string
    {
        return 'YouTube: Rate Video';
    }

    public function description(): string
    {
        return 'Likes or dislikes a video as the connected account, or removes the rating.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['video_id', 'rating'],
            'properties' => [
                ...$this->credentialFields(),
                'video_id' => Field::text('Video ID or link', 'The ID from the video\'s link (watch?v=...), or the whole link.', 'dQw4w9WgXcQ'),
                'rating' => Field::select('Rating', ['like' => 'Like', 'dislike' => 'Dislike', 'none' => 'Remove my rating'], null, 'like'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $videoId = $this->videoIdFromLink((string) $config['video_id']);

        $this->post($run, '/videos/rate?'.http_build_query(['id' => $videoId, 'rating' => $config['rating']]), $config);

        return ['video_id' => $videoId, 'rating' => $config['rating']];
    }
}
