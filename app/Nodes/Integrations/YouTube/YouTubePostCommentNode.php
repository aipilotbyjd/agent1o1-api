<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class YouTubePostCommentNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_post_comment';
    }

    public function name(): string
    {
        return 'YouTube: Post Comment';
    }

    public function description(): string
    {
        return 'Comments on a video as the connected account.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['video_id', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'video_id' => Field::text('Video ID or link', 'The ID from the video\'s link (watch?v=...), or the whole link.', 'dQw4w9WgXcQ'),
                'text' => Field::textarea('Comment'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $videoId = $this->videoIdFromLink((string) $config['video_id']);

        $response = $this->post($run, '/commentThreads?part=snippet', $config, [
            'snippet' => [
                'videoId' => $videoId,
                'topLevelComment' => ['snippet' => ['textOriginal' => $config['text']]],
            ],
        ]);

        $comment = (array) ($response['snippet']['topLevelComment']['snippet'] ?? []);

        return [
            'comment_id' => $response['id'] ?? null,
            'video_id' => $videoId,
            'text' => $comment['textDisplay'] ?? $config['text'],
            'published_at' => $comment['publishedAt'] ?? null,
        ];
    }
}
