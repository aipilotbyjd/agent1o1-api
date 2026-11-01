<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubGetPullRequestNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_get_pull_request';
    }

    public function name(): string
    {
        return 'GitHub: Get Pull Request';
    }

    public function description(): string
    {
        return 'Reads one pull request: its state, branches, author and whether it can be merged.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'pull_number'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'pull_number' => Field::integer('Pull request number', 'The number after # in the pull request\'s link.', null, 1),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        return $this->get($run, "/repos/{$repo}/pulls/{$config['pull_number']}", $config);
    }
}
