<?php

namespace App\Nodes\Integrations\Slack;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class SlackListNewUsersNode extends AbstractSlackNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * Pages of 200 people read at most.
     */
    private const int MAX_PAGES = 5;

    public function type(): string
    {
        return 'slack_list_new_users';
    }

    public function name(): string
    {
        return 'Slack: List People';
    }

    public function description(): string
    {
        return 'Lists the people in the workspace, without bots and deactivated accounts.';
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
                'include_guests' => Field::boolean('Include guests', 'Guest accounts count as people too.', true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $people = [];
        $cursor = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->get($run, 'users.list', $config, array_filter(['limit' => 200, 'cursor' => $cursor], fn ($value) => $value !== null));

            foreach ((array) ($response['members'] ?? []) as $member) {
                if (! is_array($member) || ($member['deleted'] ?? false) || ($member['is_bot'] ?? false) || ($member['id'] ?? '') === 'USLACKBOT') {
                    continue;
                }

                if (! ($config['include_guests'] ?? true) && (($member['is_restricted'] ?? false) || ($member['is_ultra_restricted'] ?? false))) {
                    continue;
                }

                $people[] = $member;
            }

            $cursor = $response['response_metadata']['next_cursor'] ?? '';

            if ($cursor === '' || $cursor === null) {
                break;
            }
        }

        return ['members' => $people];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'members';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Slack: New person';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when someone joins the workspace.';
    }

    /**
     * People join rarely and Slack tells us the moment one does: a slow check.
     */
    public function triggerPollMinutes(): int
    {
        return 30;
    }
}
