<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubMergePullRequestNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_merge_pull_request';
    }

    public function name(): string
    {
        return 'GitHub: Merge Pull Request';
    }

    public function description(): string
    {
        return 'Merges a pull request.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
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
                'merge_method' => Field::select('How to merge', ['merge' => 'Merge commit', 'squash' => 'Squash and merge', 'rebase' => 'Rebase and merge'], null, 'merge'),
                'commit_title' => Field::advanced(Field::text('Commit title', 'Leave empty for GitHub\'s default.')),
                'commit_message' => Field::advanced(Field::textarea('Commit message', 'Leave empty for GitHub\'s default.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        return $this->put($run, "/repos/{$repo}/pulls/{$config['pull_number']}/merge", $config, [
            'merge_method' => $config['merge_method'] ?? 'merge',
            'commit_title' => trim((string) ($config['commit_title'] ?? '')) ?: null,
            'commit_message' => trim((string) ($config['commit_message'] ?? '')) ?: null,
        ]);
    }
}
