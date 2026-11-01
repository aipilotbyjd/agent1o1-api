<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubStarRepositoryNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_star_repository';
    }

    public function name(): string
    {
        return 'GitHub: Star Repository';
    }

    public function description(): string
    {
        return 'Stars a repository for the connected account.';
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

        $this->put($run, "/user/starred/{$repo}", $config);

        return ['starred' => true, 'repo' => $repo];
    }
}
