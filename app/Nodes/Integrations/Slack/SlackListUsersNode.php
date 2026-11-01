<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackListUsersNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_list_users';
    }

    public function name(): string
    {
        return 'Slack: List Users';
    }

    public function description(): string
    {
        return 'Lists users in the Slack workspace.';
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
                'limit' => Field::integer('Limit', 'Maximum users to return.', 100, 1, 1000),
                'cursor' => Field::advanced(Field::text('Cursor', 'next_cursor from a previous page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, 'users.list', $config, array_filter([
            'limit' => $config['limit'] ?? null,
            'cursor' => $config['cursor'] ?? null,
        ], fn ($value) => $value !== null));
    }
}
