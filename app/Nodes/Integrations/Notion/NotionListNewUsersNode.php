<?php

namespace App\Nodes\Integrations\Notion;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;

class NotionListNewUsersNode extends AbstractNotionNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'notion_list_new_users';
    }

    public function name(): string
    {
        return 'Notion: List New Users';
    }

    public function description(): string
    {
        return 'Lists the people in the workspace, newest last.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),

            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $users = array_values(array_filter(
            array_map($this->shapeUser(...), $this->list($run, 'get', '/users', $config, [], 500)),
            fn (array $user): bool => $user['type'] === 'person',
        ));

        return ['users' => $users];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'users';
    }

    /**
     * The last people of the list: new members are added at its end.
     */
    public function triggerItems(array $output, array $config): array
    {
        $users = array_values(array_filter((array) ($output['users'] ?? []), 'is_array'));

        return array_slice($users, -max(1, (int) config('triggers.poll_max_items')));
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Notion: New workspace member';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when someone joins the Notion workspace.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }

    /**
     * Oldest first.
     */
    public function triggerListsNewestFirst(): bool
    {
        return false;
    }
}
