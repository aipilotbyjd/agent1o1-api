<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubUpdateCommentNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_update_comment';
    }

    public function name(): string
    {
        return 'GitHub: Update Comment';
    }

    public function description(): string
    {
        return 'Edits a comment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'comment_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'comment_id' => Field::integer('Comment ID', 'The "id" of the comment, from List Issue Comments.', null, 1),
                'body' => Field::textarea('New text', 'Markdown supported.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        return $this->patch($run, "/repos/{$repo}/issues/comments/{$config['comment_id']}", $config, ['body' => $config['body']]);
    }
}
