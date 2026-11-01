<?php

namespace App\Nodes\Integrations\GitHub;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GitHubListIssuesNode extends AbstractGitHubNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'github_list_issues';
    }

    public function name(): string
    {
        return 'GitHub: List Issues';
    }

    public function description(): string
    {
        return 'Lists issues in a repository.';
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
                'state' => Field::select('State', ['open' => 'Open', 'closed' => 'Closed', 'all' => 'All'], default: 'open'),
                'labels' => Field::dynamic('Only with labels', 'github.labels', 'Only issues that have all of these labels.', dependsOn: ['repo'], multiple: true, type: 'array'),
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['issues' => $this->get($run, "/repos/{$this->repo($config)}/issues", $config, [
            'state' => $config['state'] ?? 'open',
            'labels' => implode(',', array_filter((array) ($config['labels'] ?? []))) ?: null,
            'per_page' => $config['per_page'] ?? 30,
        ])];
    }

    /**
     * Read a wider page than the node's default, so a burst between two polls
     * isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'per_page' => 100];
    }

    /**
     * Issues only: GitHub's issues list also holds every pull request (they
     * carry a `pull_request` key), which have a trigger of their own.
     */
    public function triggerItems(array $output, array $config): array
    {
        return array_values(array_filter(
            (array) ($output['issues'] ?? []),
            fn (mixed $issue): bool => is_array($issue) && ! isset($issue['pull_request']),
        ));
    }

    /**
     * Newest first.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'issues';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'GitHub: New issue';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a new issue is opened in the repository.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }
}
