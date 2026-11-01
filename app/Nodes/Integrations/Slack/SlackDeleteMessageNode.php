<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackDeleteMessageNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_delete_message';
    }

    public function name(): string
    {
        return 'Slack: Delete Message';
    }

    public function description(): string
    {
        return 'Deletes a message the app posted.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'ts'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'Pick a channel, or enter a channel ID.', 'C0123456789'),
                'ts' => Field::text('Message timestamp', 'The "ts" of the message, such as 1700000000.000100.', '1700000000.000100'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'chat.delete', $config, ['channel' => $config['channel'], 'ts' => $config['ts']]);
    }
}
