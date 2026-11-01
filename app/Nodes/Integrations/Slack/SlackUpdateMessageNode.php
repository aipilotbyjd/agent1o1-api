<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackUpdateMessageNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_update_message';
    }

    public function name(): string
    {
        return 'Slack: Update Message';
    }

    public function description(): string
    {
        return 'Edits a message the app posted earlier.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'ts', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'Pick a channel, or enter a channel ID.', 'C0123456789'),
                'ts' => Field::text('Message timestamp', 'The "ts" of the message, such as 1700000000.000100.', '1700000000.000100'),
                'text' => Field::textarea('New text', 'Replaces the message.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'chat.update', $config, ['channel' => $config['channel'], 'ts' => $config['ts'], 'text' => $config['text']]);
    }
}
