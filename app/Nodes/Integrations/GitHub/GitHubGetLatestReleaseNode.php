<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubGetLatestReleaseNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_get_latest_release';
    }

    public function name(): string
    {
        return 'GitHub: Get Latest Release';
    }

    public function description(): string
    {
        return 'Reads the most recent published release.';
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
        $repo = $this->repo($config);

        return $this->get($run, "/repos/{$repo}/releases/latest", $config);
    }
}
