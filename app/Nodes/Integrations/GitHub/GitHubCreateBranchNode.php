<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubCreateBranchNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_create_branch';
    }

    public function name(): string
    {
        return 'GitHub: Create Branch';
    }

    public function description(): string
    {
        return 'Creates a branch from another branch.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'name' => Field::text('New branch name', null, 'feature/login'),
                'from' => Field::dynamic('Create from', 'github.branches', 'Defaults to the default branch.', dependsOn: ['repo']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        $from = trim((string) ($config['from'] ?? '')) ?: null ?? $this->get($run, "/repos/{$repo}", $config)['default_branch'];
        $base = $this->get($run, "/repos/{$repo}/git/ref/heads/".$this->segment((string) $from), $config);

        $ref = $this->post($run, "/repos/{$repo}/git/refs", $config, [
            'ref' => 'refs/heads/'.trim((string) $config['name']),
            'sha' => $base['object']['sha'],
        ]);

        return ['name' => trim((string) $config['name']), 'sha' => $ref['object']['sha'] ?? $base['object']['sha'], 'from' => $from];
    }
}
