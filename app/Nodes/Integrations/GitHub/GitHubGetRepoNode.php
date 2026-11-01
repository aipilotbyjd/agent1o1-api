<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubGetRepoNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_get_repo';
    }

    public function name(): string
    {
        return 'GitHub: Get Repository';
    }

    public function description(): string
    {
        return 'Gets details of a specific repository.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        return $this->get($run, "/repos/{$this->repo($config)}", $config);
    }
}
