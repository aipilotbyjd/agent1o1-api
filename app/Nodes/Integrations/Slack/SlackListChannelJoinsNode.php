<?php

namespace App\Nodes\Integrations\Slack;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class SlackListChannelJoinsNode extends AbstractSlackNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far back a read looks for people who joined.
     */
    private const int LOOKBACK_HOURS = 6;

    public function type(): string
    {
        return 'slack_list_channel_joins';
    }

    public function name(): string
    {
        return 'Slack: List Channel Joins';
    }

    public function description(): string
    {
        return 'Lists the people who joined a channel lately.';
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
                'channel' => Field::dynamic('Channel', 'slack.channels', 'The channel to watch. Pick one, or enter a channel ID.', 'C0123456789'),
                'joined_within_hours' => Field::integer('Joined within (hours)', 'Only people who joined this recently.', 24, 1, 720),
            ],
        ];
    }

    /**
     * Slack posts a "joined the channel" message when someone joins; the
     * history holds them.
     */
    public function execute(Run $run, array $config, array $context): array
    {
        $history = $this->get($run, 'conversations.history', $config, [
            'channel' => $config['channel'],
            'oldest' => now()->subHours((int) ($config['joined_within_hours'] ?? 24))->getTimestamp(),
            'limit' => 200,
        ]);

        $joins = [];

        foreach ((array) ($history['messages'] ?? []) as $message) {
            if (is_array($message) && ($message['subtype'] ?? null) === 'channel_join' && filled($message['user'] ?? null)) {
                $joins[] = [
                    'channel' => $config['channel'],
                    'user' => $message['user'],
                    'ts' => $message['ts'] ?? null,
                    'inviter' => $message['inviter'] ?? null,
                ];
            }
        }

        return ['joins' => $joins];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'joins';
    }

    /**
     * A person joining is named by the channel, the person and the moment.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['channel'] ?? '').':'.($item['user'] ?? '').':'.($item['ts'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Slack: Person joined channel';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when someone joins the channel.';
    }

    /**
     * Look back the full window, whatever the node is set to.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'joined_within_hours' => self::LOOKBACK_HOURS];
    }

    /**
     * Newest first, as the history is listed.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * Slack tells us the moment someone joins: a slow check.
     */
    public function triggerPollMinutes(): int
    {
        return 10;
    }
}
