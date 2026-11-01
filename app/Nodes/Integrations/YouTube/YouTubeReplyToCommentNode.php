<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class YouTubeReplyToCommentNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_reply_to_comment';
    }

    public function name(): string
    {
        return 'YouTube: Reply to Comment';
    }

    public function description(): string
    {
        return 'Replies to a comment as the connected account.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['comment_id', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'comment_id' => Field::text('Comment ID', 'The comment to reply to: the comment_id from List Comments or the New comment trigger.', 'Ugxxxxxxxxxxxxxxxxxxxxxxxxxx'),
                'text' => Field::textarea('Reply'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->post($run, '/comments?part=snippet', $config, [
            'snippet' => ['parentId' => $config['comment_id'], 'textOriginal' => $config['text']],
        ]);

        return [
            'reply_id' => $response['id'] ?? null,
            'comment_id' => $config['comment_id'],
            'text' => $response['snippet']['textDisplay'] ?? $config['text'],
            'published_at' => $response['snippet']['publishedAt'] ?? null,
        ];
    }
}
