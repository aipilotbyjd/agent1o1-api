<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubGetUserNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_get_user';
    }

    public function name(): string
    {
        return 'GitHub: Get User';
    }

    public function description(): string
    {
        return 'Reads a GitHub person or organization. Leave the name empty for the connected account.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'username' => Field::text('Username', 'Leave empty for the connected account.', 'octocat'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $username = trim((string) ($config['username'] ?? '')) ?: null;

        return $this->get($run, $username === null ? '/user' : '/users/'.rawurlencode($username), $config);
    }
}
