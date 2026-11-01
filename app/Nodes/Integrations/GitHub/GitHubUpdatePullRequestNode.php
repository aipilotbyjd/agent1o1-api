<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GitHubUpdatePullRequestNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_update_pull_request';
    }

    public function name(): string
    {
        return 'GitHub: Update Pull Request';
    }

    public function description(): string
    {
        return 'Edits a pull request: its title, text, state or base branch. Fields you leave empty stay as they are.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
                'title' => Field::text('Title', 'Leave empty to keep it.'),
                'body' => Field::textarea('Description', 'Markdown supported. Leave empty to keep it.'),
                'state' => Field::select('State', ['open' => 'Open', 'closed' => 'Closed'], 'Leave empty to keep it.'),
                'base' => Field::dynamic('Base branch', 'github.branches', 'Leave empty to keep it.', dependsOn: ['repo']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        $changes = array_filter([
            'title' => trim((string) ($config['title'] ?? '')) ?: null,
            'body' => trim((string) ($config['body'] ?? '')) ?: null,
            'state' => trim((string) ($config['state'] ?? '')) ?: null,
            'base' => trim((string) ($config['base'] ?? '')) ?: null,
        ], fn ($value) => $value !== null);

        if ($changes === []) {
            throw new InvalidArgumentException('GitHub: choose at least one field to change.');
        }

        return $this->patch($run, "/repos/{$repo}/pulls/{$config['pull_number']}", $config, $changes);
    }
}
