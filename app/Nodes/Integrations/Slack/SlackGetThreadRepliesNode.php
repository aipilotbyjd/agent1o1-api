<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackGetThreadRepliesNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_get_thread_replies';
    }

    public function name(): string
    {
        return 'Slack: Get Thread Replies';
    }

    public function description(): string
    {
        return 'Reads a message and the replies under it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'thread_ts'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'Pick a channel, or enter a channel ID.', 'C0123456789'),
                'thread_ts' => Field::text('Thread timestamp', 'The "ts" of the message that started the thread.', '1700000000.000100'),
                'limit' => Field::integer('Limit', 'Maximum messages to return.', 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, 'conversations.replies', $config, [
            'channel' => $config['channel'],
            'ts' => $config['thread_ts'],
            'limit' => $config['limit'] ?? 100,
        ]);
    }
}
