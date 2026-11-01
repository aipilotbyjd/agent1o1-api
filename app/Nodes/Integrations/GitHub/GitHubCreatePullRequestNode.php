<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubCreatePullRequestNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_create_pull_request';
    }

    public function name(): string
    {
        return 'GitHub: Create Pull Request';
    }

    public function description(): string
    {
        return 'Creates a new pull request in a repository.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'title', 'head', 'base'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'title' => Field::text('Title'),
                'head' => Field::dynamic('From branch', 'github.branches', 'The branch with your changes.', 'feature/my-change', dependsOn: ['repo']),
                'base' => Field::dynamic('Into branch', 'github.branches', 'The branch to merge into.', 'main', dependsOn: ['repo']),
                'body' => Field::textarea('Description', 'Markdown supported.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $data = $this->post($run, "/repos/{$this->repo($config)}/pulls", $config, [
            'title' => $config['title'],
            'head' => $config['head'],
            'base' => $config['base'],
            'body' => $config['body'] ?? '',
        ]);

        return [
            'id' => $data['id'] ?? null,
            'number' => $data['number'] ?? null,
            'title' => $data['title'] ?? null,
            'url' => $data['html_url'] ?? null,
        ];
    }
}
