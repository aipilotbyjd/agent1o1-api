<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubCreateCommentNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_create_comment';
    }

    public function name(): string
    {
        return 'GitHub: Create Comment';
    }

    public function description(): string
    {
        return 'Adds a comment to an issue or pull request.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'issue_number', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'issue_number' => Field::dynamic('Issue or pull request', 'github.issues', dependsOn: ['repo'], type: 'integer'),
                'body' => Field::textarea('Comment', 'Markdown supported.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $data = $this->post($run, "/repos/{$this->repo($config)}/issues/{$config['issue_number']}/comments", $config, [
            'body' => $config['body'],
        ]);

        return [
            'id' => $data['id'] ?? null,
            'url' => $data['html_url'] ?? null,
        ];
    }
}
