<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubDeleteCommentNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_delete_comment';
    }

    public function name(): string
    {
        return 'GitHub: Delete Comment';
    }

    public function description(): string
    {
        return 'Deletes a comment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'comment_id'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'comment_id' => Field::integer('Comment ID', 'The "id" of the comment, from List Issue Comments.', null, 1),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        $this->delete($run, "/repos/{$repo}/issues/comments/{$config['comment_id']}", $config);

        return ['deleted' => true, 'comment_id' => $config['comment_id']];
    }
}
