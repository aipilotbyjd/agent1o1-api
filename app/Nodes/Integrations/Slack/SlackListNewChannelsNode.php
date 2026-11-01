<?php

namespace App\Nodes\Integrations\Slack;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class SlackListNewChannelsNode extends AbstractSlackNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * Pages of 200 channels read at most, so a huge workspace costs a few calls.
     */
    private const int MAX_PAGES = 5;

    /**
     * How recently a channel must have been made to count as new.
     */
    private const int NEW_WITHIN_HOURS = 24;

    public function type(): string
    {
        return 'slack_list_new_channels';
    }

    public function name(): string
    {
        return 'Slack: List Channels by Creation Time';
    }

    public function description(): string
    {
        return 'Lists the channels made recently, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'types' => Field::select('Channel types', ['public_channel' => 'Public channels', 'public_channel,private_channel' => 'Public and private'], null, 'public_channel'),
                'created_within_hours' => Field::integer('Made within (hours)', 'Only channels made this recently.', 24, 1, 8760),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = now()->subHours((int) ($config['created_within_hours'] ?? self::NEW_WITHIN_HOURS))->getTimestamp();
        $channels = [];
        $cursor = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->get($run, 'conversations.list', $config, array_filter([
                'types' => $config['types'] ?? 'public_channel',
                'exclude_archived' => 'true',
                'limit' => 200,
                'cursor' => $cursor,
            ], fn ($value) => $value !== null));

            foreach ((array) ($response['channels'] ?? []) as $channel) {
                if (is_array($channel) && (int) ($channel['created'] ?? 0) >= $since) {
                    $channels[] = $channel;
                }
            }

            $cursor = $response['response_metadata']['next_cursor'] ?? '';

            if ($cursor === '' || $cursor === null) {
                break;
            }
        }

        usort($channels, fn (array $a, array $b): int => ($b['created'] ?? 0) <=> ($a['created'] ?? 0));

        return ['channels' => $channels];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'channels';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Slack: New channel';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a channel is created in the workspace.';
    }

    /**
     * Newest first, as the list is sorted.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * Channels are made rarely and Slack tells us the moment one is: a slow check.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }
}
