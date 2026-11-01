<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubGetCommitNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_get_commit';
    }

    public function name(): string
    {
        return 'GitHub: Get Commit';
    }

    public function description(): string
    {
        return 'Reads one commit: its message, author and the files it changed.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'ref'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'ref' => Field::text('Commit', 'A commit SHA, branch or tag name.', 'main'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        return $this->get($run, "/repos/{$repo}/commits/".$this->segment((string) $config['ref']), $config);
    }
}
