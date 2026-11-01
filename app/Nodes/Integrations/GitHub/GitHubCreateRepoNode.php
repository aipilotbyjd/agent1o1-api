<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubCreateRepoNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_create_repo';
    }

    public function name(): string
    {
        return 'GitHub: Create Repo';
    }

    public function description(): string
    {
        return 'Creates a new repository for the authenticated user or an organization.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'owner' => Field::dynamic('Organization', 'github.owners', 'Leave empty to create it under your own account.'),
                'name' => Field::text('Name', null, 'my-new-repo'),
                'description' => Field::text('Description'),
                'private' => Field::boolean('Private'),
                'auto_init' => Field::boolean('Add a README', 'Initialise the repository with a first commit.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $endpoint = isset($config['owner']) ? "/orgs/{$config['owner']}/repos" : '/user/repos';

        $data = $this->post($run, $endpoint, $config, [
            'name' => $config['name'],
            'description' => $config['description'] ?? null,
            'private' => $config['private'] ?? false,
            'auto_init' => $config['auto_init'] ?? false,
        ]);

        return [
            'id' => $data['id'] ?? null,
            'full_name' => $data['full_name'] ?? null,
            'private' => $data['private'] ?? null,
            'url' => $data['html_url'] ?? null,
        ];
    }
}
