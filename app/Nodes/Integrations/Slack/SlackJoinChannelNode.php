<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackJoinChannelNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_join_channel';
    }

    public function name(): string
    {
        return 'Slack: Join Channel';
    }

    public function description(): string
    {
        return 'Adds the app to a public channel.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'Pick a channel, or enter a channel ID.', 'C0123456789'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'conversations.join', $config, ['channel' => $config['channel']]);
    }
}
