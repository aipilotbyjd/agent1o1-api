<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackAddReactionNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_add_reaction';
    }

    public function name(): string
    {
        return 'Slack: Add Reaction';
    }

    public function description(): string
    {
        return 'Adds an emoji reaction to a message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'ts', 'emoji'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'Pick a channel, or enter a channel ID.', 'C0123456789'),
                'ts' => Field::text('Message timestamp', 'The "ts" of the message, such as 1700000000.000100.', '1700000000.000100'),
                'emoji' => Field::text('Emoji', 'The emoji name without colons.', 'white_check_mark'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'reactions.add', $config, [
            'channel' => $config['channel'],
            'timestamp' => $config['ts'],
            'name' => trim((string) $config['emoji'], ": \t\n\r"),
        ]);
    }
}
