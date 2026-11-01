<?php

namespace App\Nodes\Integrations\GitHub;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GitHubListMergedPullRequestsNode extends AbstractGitHubNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'github_list_merged_pull_requests';
    }

    public function name(): string
    {
        return 'GitHub: List Merged Pull Requests';
    }

    public function description(): string
    {
        return 'Lists the pull requests that were merged lately.';
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
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        return ['pull_requests' => $this->get($run, "/repos/{$repo}/pulls", $config, ['state' => 'closed', 'sort' => 'updated', 'direction' => 'desc', 'per_page' => $config['per_page'] ?? 30])];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'pull_requests';
    }

    /**
     * The item's stable id.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['number'] ?? '');
    }

    /**
     * Merged pull requests only: closed without merging does not count.
     */
    public function triggerItems(array $output, array $config): array
    {
        return array_values(array_filter(
            (array) ($output['pull_requests'] ?? []),
            fn (mixed $item): bool => is_array($item) && filled($item['merged_at'] ?? null),
        ));
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'GitHub: Pull request merged';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a pull request is merged.';
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
     * Newest first, as GitHub lists them.
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
