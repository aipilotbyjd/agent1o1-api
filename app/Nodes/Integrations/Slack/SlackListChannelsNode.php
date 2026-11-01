<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackListChannelsNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_list_channels';
    }

    public function name(): string
    {
        return 'Slack: List Channels';
    }

    public function description(): string
    {
        return 'Lists channels in the Slack workspace, optionally filtered by type.';
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
                'types' => Field::select('Channel types', ['public_channel' => 'Public channels', 'private_channel' => 'Private channels', 'public_channel,private_channel' => 'Public and private', 'im' => 'Direct messages', 'mpim' => 'Group direct messages'], default: 'public_channel'),
                'limit' => Field::integer('Limit', 'Maximum channels to return.', 100, 1, 1000),
                'cursor' => Field::advanced(Field::text('Cursor', 'next_cursor from a previous page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, 'conversations.list', $config, array_filter([
            'types' => $config['types'] ?? null,
            'limit' => $config['limit'] ?? null,
            'cursor' => $config['cursor'] ?? null,
        ], fn ($value) => $value !== null));
    }
}
