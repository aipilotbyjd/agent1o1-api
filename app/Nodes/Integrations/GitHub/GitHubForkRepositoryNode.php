<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubForkRepositoryNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_fork_repository';
    }

    public function name(): string
    {
        return 'GitHub: Fork Repository';
    }

    public function description(): string
    {
        return 'Forks a repository to the connected account or an organization.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'organization' => Field::text('Organization', 'Leave empty to fork to your own account.', 'acme'),
                'name' => Field::text('Name of the fork', 'Defaults to the original name.'),
                'default_branch_only' => Field::boolean('Copy only the default branch'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        return $this->post($run, "/repos/{$repo}/forks", $config, [
            'organization' => trim((string) ($config['organization'] ?? '')) ?: null,
            'name' => trim((string) ($config['name'] ?? '')) ?: null,
            'default_branch_only' => ($config['default_branch_only'] ?? false) ? true : null,
        ]);
    }
}
