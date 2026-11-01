<?php

namespace App\Nodes\Integrations\Slack;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class SlackListMentionsNode extends AbstractSlackNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far back a read looks for mentions.
     */
    private const int LOOKBACK_HOURS = 6;

    public function type(): string
    {
        return 'slack_list_mentions';
    }

    public function name(): string
    {
        return 'Slack: List Mentions of the App';
    }

    public function description(): string
    {
        return 'Lists the messages in a channel that mention the connected app (@name).';
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
                'mentioned_within_hours' => Field::integer('Mentioned within (hours)', 'Only mentions this recent.', 24, 1, 720),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $appUser = (string) $this->get($run, 'auth.test', $config)['user_id'];

        $history = $this->get($run, 'conversations.history', $config, [
            'channel' => $config['channel'],
            'oldest' => now()->subHours((int) ($config['mentioned_within_hours'] ?? 24))->getTimestamp(),
            'limit' => 200,
        ]);

        $mentions = [];

        foreach ((array) ($history['messages'] ?? []) as $message) {
            if (is_array($message) && ! isset($message['bot_id']) && str_contains((string) ($message['text'] ?? ''), "<@{$appUser}") && ($message['subtype'] ?? null) === null) {
                $mentions[] = [...$message, 'channel' => $config['channel']];
            }
        }

        return ['mentions' => $mentions];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'mentions';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Slack: App mentioned';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when someone mentions the app (@name) in the channel.';
    }

    /**
     * Look back the full window, whatever the node is set to.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'mentioned_within_hours' => self::LOOKBACK_HOURS];
    }

    /**
     * The message timestamp is the mention's id.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['ts'] ?? '');
    }

    /**
     * Newest first, as the history is listed.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * Slack tells us the moment the app is mentioned: a slow check.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }
}
