<?php

namespace App\Nodes\Integrations\GitHub;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GitHubListReposNode extends AbstractGitHubNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'github_list_repos';
    }

    public function name(): string
    {
        return 'GitHub: List Repos';
    }

    public function description(): string
    {
        return 'Lists repositories for the authenticated user or an organization.';
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
                'owner' => Field::dynamic('Organization', 'github.owners', 'Leave empty to list your own repositories.'),
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $endpoint = isset($config['owner']) ? "/orgs/{$config['owner']}/repos" : '/user/repos';

        return ['repos' => $this->get($run, $endpoint, $config, ['per_page' => $config['per_page'] ?? 30, 'sort' => $config['sort'] ?? null, 'direction' => $config['direction'] ?? null])];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'repos';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'GitHub: New repository';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a repository is created on your account, or in the chosen organization.';
    }

    /**
     * Newest first, and a wide page. GitHub has no webhook for an account's
     * new repositories, so this one checks on a timer.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'sort' => 'created', 'direction' => 'desc', 'per_page' => 100];
    }

    /**
     * Newest first, as the list is requested.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * Repositories are made rarely and there is no push for them: a slow check.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }
}
