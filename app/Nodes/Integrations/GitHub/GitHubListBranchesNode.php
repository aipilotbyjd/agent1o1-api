<?php

namespace App\Nodes\Integrations\GitHub;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GitHubListBranchesNode extends AbstractGitHubNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'github_list_branches';
    }

    public function name(): string
    {
        return 'GitHub: List Branches';
    }

    public function description(): string
    {
        return 'Lists a repository’s branches.';
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
                'protected_only' => Field::boolean('Only protected branches'),
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        return ['branches' => $this->get($run, "/repos/{$repo}/branches", $config, [
            'protected' => ($config['protected_only'] ?? false) ? 'true' : null,
            'per_page' => $config['per_page'] ?? 30,
        ])];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'branches';
    }

    /**
     * The item's stable id.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['name'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'GitHub: New branch';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a branch is created.';
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
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }
}
