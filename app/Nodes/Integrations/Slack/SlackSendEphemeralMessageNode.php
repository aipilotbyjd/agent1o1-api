<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackSendEphemeralMessageNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_send_ephemeral_message';
    }

    public function name(): string
    {
        return 'Slack: Send Message Only One Person Sees';
    }

    public function description(): string
    {
        return 'Posts a message in a channel that only one person can see.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'user', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'Pick a channel, or enter a channel ID.', 'C0123456789'),
                'user' => Field::dynamic('Person', 'slack.users', 'Pick a person, or enter a Slack user ID.', 'U0123456789'),
                'text' => Field::textarea('Message', 'Supports Slack mrkdwn and {{templates}}.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'chat.postEphemeral', $config, ['channel' => $config['channel'], 'user' => $config['user'], 'text' => $config['text']]);
    }
}
