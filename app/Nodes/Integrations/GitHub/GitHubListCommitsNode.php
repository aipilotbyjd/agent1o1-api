<?php

namespace App\Nodes\Integrations\GitHub;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GitHubListCommitsNode extends AbstractGitHubNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'github_list_commits';
    }

    public function name(): string
    {
        return 'GitHub: List Commits';
    }

    public function description(): string
    {
        return 'Lists commits in a repository.';
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
                'sha' => Field::dynamic('Branch', 'github.branches', 'Defaults to the default branch.', dependsOn: ['repo']),
                'path' => Field::advanced(Field::text('Path', 'Only commits touching this file or folder.', 'src/')),
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['commits' => $this->get($run, "/repos/{$this->repo($config)}/commits", $config, [
            'sha' => $config['sha'] ?? null,
            'path' => $config['path'] ?? null,
            'per_page' => $config['per_page'] ?? 30,
        ])];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'commits';
    }

    /**
     * A commit's SHA is its id.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['sha'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'GitHub: New commit';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a commit is pushed to a branch (the default branch unless one is chosen).';
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
     * Newest first, as GitHub lists commits.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }
}
