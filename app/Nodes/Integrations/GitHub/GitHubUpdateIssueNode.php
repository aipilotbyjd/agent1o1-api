<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GitHubUpdateIssueNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_update_issue';
    }

    public function name(): string
    {
        return 'GitHub: Update Issue';
    }

    public function description(): string
    {
        return 'Edits an issue: its title, text, state, labels or assignees. Fields you leave empty stay as they are.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'issue_number'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'issue_number' => Field::dynamic('Issue or pull request', 'github.issues', dependsOn: ['repo'], type: 'integer'),
                'title' => Field::text('Title', 'Leave empty to keep it.'),
                'body' => Field::textarea('Description', 'Markdown supported. Leave empty to keep it.'),
                'state' => Field::select('State', ['open' => 'Open', 'closed' => 'Closed'], 'Leave empty to keep it.'),
                'state_reason' => Field::advanced(Field::select('Reason for closing', ['completed' => 'Completed', 'not_planned' => 'Not planned', 'reopened' => 'Reopened'])),
                'labels' => Field::dynamic('Labels', 'github.labels', 'Replaces the issue\'s labels. Leave empty to keep them.', dependsOn: ['repo'], multiple: true, type: 'array'),
                'assignees' => Field::dynamic('Assignees', 'github.assignees', 'Replaces the assignees. Leave empty to keep them.', dependsOn: ['repo'], multiple: true, type: 'array'),
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
            'state_reason' => trim((string) ($config['state_reason'] ?? '')) ?: null,
            'labels' => array_values(array_filter((array) ($config['labels'] ?? []))) ?: null,
            'assignees' => array_values(array_filter((array) ($config['assignees'] ?? []))) ?: null,
        ], fn ($value) => $value !== null);

        if ($changes === []) {
            throw new InvalidArgumentException('GitHub: choose at least one field to change.');
        }

        return $this->patch($run, "/repos/{$repo}/issues/{$config['issue_number']}", $config, $changes);
    }
}
