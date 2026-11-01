<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubUnstarRepositoryNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_unstar_repository';
    }

    public function name(): string
    {
        return 'GitHub: Unstar Repository';
    }

    public function description(): string
    {
        return 'Removes the connected account’s star from a repository.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        $this->delete($run, "/user/starred/{$repo}", $config);

        return ['starred' => false, 'repo' => $repo];
    }
}
