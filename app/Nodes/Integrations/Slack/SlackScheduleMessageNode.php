<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Carbon\CarbonImmutable;

class SlackScheduleMessageNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_schedule_message';
    }

    public function name(): string
    {
        return 'Slack: Schedule Message';
    }

    public function description(): string
    {
        return 'Posts a message at a later time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'text', 'post_at'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'Pick a channel, or enter a channel ID.', 'C0123456789'),
                'text' => Field::textarea('Message', 'Supports Slack mrkdwn and {{templates}}.'),
                'post_at' => Field::datetime('Send at', 'A time in the future, up to 120 days ahead.'),
                'thread_ts' => Field::advanced(Field::text('Reply in thread', 'The parent message timestamp (ts) to reply under.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'chat.scheduleMessage', $config, array_filter([
            'channel' => $config['channel'],
            'text' => $config['text'],
            'post_at' => CarbonImmutable::parse((string) $config['post_at'])->getTimestamp(),
            'thread_ts' => $config['thread_ts'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));
    }
}
