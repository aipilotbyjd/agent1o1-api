<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubAddLabelsNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_add_labels';
    }

    public function name(): string
    {
        return 'GitHub: Add Labels';
    }

    public function description(): string
    {
        return 'Adds labels to an issue or pull request.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'issue_number', 'labels'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'issue_number' => Field::dynamic('Issue or pull request', 'github.issues', dependsOn: ['repo'], type: 'integer'),
                'labels' => Field::dynamic('Labels', 'github.labels', dependsOn: ['repo'], multiple: true, type: 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        return ['labels' => $this->post($run, "/repos/{$repo}/issues/{$config['issue_number']}/labels", $config, ['labels' => array_values(array_filter((array) $config['labels']))])];
    }
}
