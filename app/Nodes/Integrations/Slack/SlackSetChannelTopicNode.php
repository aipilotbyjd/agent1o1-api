<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackSetChannelTopicNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_set_channel_topic';
    }

    public function name(): string
    {
        return 'Slack: Set Channel Topic';
    }

    public function description(): string
    {
        return 'Sets a channel\'s topic.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'topic'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'Pick a channel, or enter a channel ID.', 'C0123456789'),
                'topic' => Field::text('Topic', 'Up to 250 characters.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'conversations.setTopic', $config, ['channel' => $config['channel'], 'topic' => $config['topic']]);
    }
}
