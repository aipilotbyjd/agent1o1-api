<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackRemoveFromChannelNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_remove_from_channel';
    }

    public function name(): string
    {
        return 'Slack: Remove From Channel';
    }

    public function description(): string
    {
        return 'Removes a person from a channel.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'user'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'Pick a channel, or enter a channel ID.', 'C0123456789'),
                'user' => Field::dynamic('Person', 'slack.users', 'Pick a person, or enter a Slack user ID.', 'U0123456789'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'conversations.kick', $config, ['channel' => $config['channel'], 'user' => $config['user']]);
    }
}
