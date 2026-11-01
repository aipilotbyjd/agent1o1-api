<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackGetChannelInfoNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_get_channel_info';
    }

    public function name(): string
    {
        return 'Slack: Get Channel Info';
    }

    public function description(): string
    {
        return 'Reads a channel: its name, topic, purpose and member count.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        return $this->get($run, 'conversations.info', $config, ['channel' => $config['channel'], 'include_num_members' => 'true']);
    }
}
