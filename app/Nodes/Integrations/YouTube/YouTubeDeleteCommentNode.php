<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class YouTubeDeleteCommentNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_delete_comment';
    }

    public function name(): string
    {
        return 'YouTube: Delete Comment';
    }

    public function description(): string
    {
        return 'Deletes a comment or reply written by the connected account, or a comment on one of its videos.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['comment_id'],
            'properties' => [
                ...$this->credentialFields(),
                'comment_id' => Field::text('Comment ID', 'The comment_id from List Comments or the New comment trigger.', 'Ugxxxxxxxxxxxxxxxxxxxxxxxxxx'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->delete($run, '/comments?'.http_build_query(['id' => $config['comment_id']]), $config);

        return ['deleted' => true, 'comment_id' => $config['comment_id']];
    }
}
