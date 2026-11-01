<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubListPullRequestFilesNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_list_pull_request_files';
    }

    public function name(): string
    {
        return 'GitHub: List Pull Request Files';
    }

    public function description(): string
    {
        return 'Lists the files a pull request changes, with their patches.';
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
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        return ['files' => $this->get($run, "/repos/{$repo}/pulls/{$config['pull_number']}/files", $config, ['per_page' => $config['per_page'] ?? 30])];
    }
}
