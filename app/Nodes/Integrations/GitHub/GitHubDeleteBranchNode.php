<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubDeleteBranchNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_delete_branch';
    }

    public function name(): string
    {
        return 'GitHub: Delete Branch';
    }

    public function description(): string
    {
        return 'Deletes a branch.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'branch'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'branch' => Field::dynamic('Branch', 'github.branches', dependsOn: ['repo']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        $this->delete($run, "/repos/{$repo}/git/refs/heads/".$this->segment((string) $config['branch']), $config);

        return ['deleted' => true, 'branch' => $config['branch']];
    }
}
