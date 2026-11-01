<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubAddAssigneesNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_add_assignees';
    }

    public function name(): string
    {
        return 'GitHub: Add Assignees';
    }

    public function description(): string
    {
        return 'Assigns people to an issue or pull request.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'issue_number', 'assignees'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'issue_number' => Field::dynamic('Issue or pull request', 'github.issues', dependsOn: ['repo'], type: 'integer'),
                'assignees' => Field::dynamic('Assignees', 'github.assignees', dependsOn: ['repo'], multiple: true, type: 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        return $this->post($run, "/repos/{$repo}/issues/{$config['issue_number']}/assignees", $config, ['assignees' => array_values(array_filter((array) $config['assignees']))]);
    }
}
