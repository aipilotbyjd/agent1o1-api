<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubCreateIssueNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_create_issue';
    }

    public function name(): string
    {
        return 'GitHub: Create Issue';
    }

    public function description(): string
    {
        return 'Creates a new issue in a repository.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'title'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'title' => Field::text('Title'),
                'body' => Field::textarea('Description', 'Markdown supported.'),
                'labels' => Field::dynamic('Labels', 'github.labels', dependsOn: ['repo'], multiple: true, type: 'array'),
                'assignees' => Field::dynamic('Assignees', 'github.assignees', dependsOn: ['repo'], multiple: true, type: 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $data = $this->post($run, "/repos/{$this->repo($config)}/issues", $config, [
            'title' => $config['title'],
            'body' => $config['body'] ?? '',
            'labels' => $config['labels'] ?? [],
            'assignees' => $config['assignees'] ?? [],
        ]);

        return [
            'id' => $data['id'] ?? null,
            'number' => $data['number'] ?? null,
            'title' => $data['title'] ?? null,
            'url' => $data['html_url'] ?? null,
        ];
    }
}
