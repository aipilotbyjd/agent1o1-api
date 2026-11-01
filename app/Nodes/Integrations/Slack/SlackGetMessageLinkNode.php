<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackGetMessageLinkNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_get_message_link';
    }

    public function name(): string
    {
        return 'Slack: Get Message Link';
    }

    public function description(): string
    {
        return 'Returns the permanent link to a message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        return $this->get($run, 'chat.getPermalink', $config, ['channel' => $config['channel'], 'message_ts' => $config['ts']]);
    }
}
