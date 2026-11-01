<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackSendDirectMessageNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_send_direct_message';
    }

    public function name(): string
    {
        return 'Slack: Send Direct Message';
    }

    public function description(): string
    {
        return 'Sends a private message to one person.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'user' => Field::dynamic('Person', 'slack.users', 'Pick a person, or enter a Slack user ID.', 'U0123456789'),
                'text' => Field::textarea('Message', 'Supports Slack mrkdwn and {{templates}}.', 'Hi! Your report is ready.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        // A direct message goes to the conversation Slack opens (or reopens) with the person.
        $channel = $this->post($run, 'conversations.open', $config, ['users' => (string) $config['user']])['channel']['id'];

        return $this->post($run, 'chat.postMessage', $config, ['channel' => $channel, 'text' => $config['text']]);
    }
}
