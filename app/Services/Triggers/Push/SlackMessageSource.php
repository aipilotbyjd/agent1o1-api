<?php

namespace App\Services\Triggers\Push;

use App\Contracts\SupportsTriggerMode;
use App\Contracts\Triggers\ItemPushSource;
use App\Contracts\Triggers\PollsOnEvent;
use App\Exceptions\ConnectorException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Services\Connectors\ConnectorTokens;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

/**
 * "New message in a Slack channel" over the Events API.
 *
 * Events arrive for a whole Slack team, so the routing key is the team id and
 * the channel and the node's filters are applied here, per trigger. Whether an
 * item passes is decided by the Slack node's own trigger filter, so push and
 * poll can never disagree about it.
 */
class SlackMessageSource implements ItemPushSource, PollsOnEvent
{
    private const string MESSAGES_NODE = 'slack_get_channel_history';

    private const string REACTIONS_NODE = 'slack_reaction_added';

    private const string REACTIONS_REMOVED_NODE = 'slack_reaction_removed';

    private const string DIRECT_MESSAGES_NODE = 'slack_list_direct_messages';

    /**
     * Reader nodes whose trigger polls when Slack sends one of these events,
     * because the event carries too little to be the item itself.
     *
     * @var array<string, list<string>>
     */
    private const array POLLED_ON = [
        'slack_list_new_channels' => ['channel_created'],
        'slack_list_new_users' => ['team_join'],
        'slack_list_files' => ['file_shared'],
        'slack_list_channel_joins' => ['member_joined_channel'],
        'slack_list_mentions' => ['app_mention'],
        'slack_list_new_pins' => ['pin_added'],
    ];

    /**
     * Reader nodes whose items the events carry themselves.
     *
     * @var list<string>
     */
    private const array DELIVERED_NODES = [self::MESSAGES_NODE, self::REACTIONS_NODE, self::REACTIONS_REMOVED_NODE, self::DIRECT_MESSAGES_NODE];

    public function __construct(
        private readonly NodeRegistry $registry,
        private readonly ConnectorTokens $tokens,
    ) {}

    /**
     * The provider key.
     */
    public function provider(): string
    {
        return 'slack';
    }

    /**
     * On when the Slack push switch is on and the platform app's signing
     * secret is set.
     */
    public function isEnabled(): bool
    {
        return (bool) config('triggers.push.slack.enabled') && filled(config('triggers.push.slack.signing_secret'));
    }

    /**
     * A Slack channel-message or reaction trigger reading through a connected account.
     */
    public function supports(Trigger $trigger): bool
    {
        $nodeType = $trigger->config['node_type'] ?? null;

        return (in_array($nodeType, self::DELIVERED_NODES, true) || isset(self::POLLED_ON[$nodeType])) && $trigger->credential_id !== null;
    }

    /**
     * Whether the event is one the trigger polls on: the right kind of event,
     * and (for a channel trigger) in the trigger's channel.
     */
    public function wantsPoll(Trigger $trigger, array $event): bool
    {
        $nodeType = (string) ($trigger->config['node_type'] ?? '');

        if (! in_array($event['type'] ?? null, self::POLLED_ON[$nodeType] ?? [], true)) {
            return false;
        }

        $watched = trim((string) ($trigger->config['node_config']['channel'] ?? ''));
        // Most events name their channel by id; `channel_created` carries the channel itself.
        $channel = $event['channel'] ?? $event['channel_id'] ?? '';
        $inChannel = is_string($channel) ? $channel : '';

        // A trigger with no channel (new channels, new people, files anywhere) takes every event.
        return $watched === '' || $watched === $inChannel;
    }

    /**
     * The Slack team id of the trigger's account, learned once with
     * `auth.test` and kept on the account.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        $credential = ConnectorCredential::query()
            ->where('workspace_id', $trigger->workspace_id)
            ->find($trigger->credential_id);

        if ($credential === null) {
            return null;
        }

        $known = $credential->data['team_id'] ?? null;

        if (filled($known)) {
            return (string) $known;
        }

        $response = Http::withToken($this->tokens->accessToken($credential))
            ->timeout(15)
            ->post('https://slack.com/api/auth.test');

        $teamId = $response->json('team_id');

        if ($response->json('ok') !== true || ! is_string($teamId) || $teamId === '') {
            throw new ConnectorException('Slack did not accept the connected account: '.($response->json('error') ?? 'unknown error').'. Reconnect Slack.');
        }

        $credential->update(['data' => [...$credential->data, 'team_id' => $teamId]]);

        return $teamId;
    }

    /**
     * Slack only sends channel messages to an app that is in the channel, so
     * say so when it is not.
     */
    public function accessWarning(Trigger $trigger): ?string
    {
        $credential = ConnectorCredential::query()
            ->where('workspace_id', $trigger->workspace_id)
            ->find($trigger->credential_id);
        $channel = $trigger->config['node_config']['channel'] ?? null;

        if ($credential === null || ! filled($channel)) {
            return null;
        }

        try {
            $response = Http::withToken($this->tokens->accessToken($credential))
                ->timeout(15)
                ->get('https://slack.com/api/conversations.info', ['channel' => $channel]);
        } catch (\Throwable) {
            return null;
        }

        if ($response->json('ok') !== true) {
            return null;
        }

        $name = $response->json('channel.name');

        return $response->json('channel.is_member') === false
            ? 'Add the app to '.(is_string($name) ? "#{$name}" : 'the channel').' so it can see new messages. Until then it checks on a timer.'
            : null;
    }

    /**
     * Whether the message is in the trigger's channel and passes the node's
     * bot, reply and channel-event filters.
     */
    public function accepts(Trigger $trigger, array $item): bool
    {
        $nodeConfig = (array) ($trigger->config['node_config'] ?? []);
        $nodeType = (string) ($trigger->config['node_type'] ?? '');

        // Only these triggers take items; the rest poll (see wantsPoll).
        if (! in_array($nodeType, self::DELIVERED_NODES, true)) {
            return false;
        }

        $isReaction = isset($item['emoji']);

        // A reaction trigger takes reactions (added or removed, as it is for), the others take messages.
        if ($isReaction !== in_array($nodeType, [self::REACTIONS_NODE, self::REACTIONS_REMOVED_NODE], true)) {
            return false;
        }

        if ($isReaction && isset($item['removed']) !== ($nodeType === self::REACTIONS_REMOVED_NODE)) {
            return false;
        }

        // A direct message trigger takes messages in any conversation with the app; the others one channel.
        if ($nodeType === self::DIRECT_MESSAGES_NODE) {
            if (! str_starts_with((string) ($item['channel'] ?? ''), 'D')) {
                return false;
            }
        } elseif (($item['channel'] ?? null) !== ($nodeConfig['channel'] ?? null)) {
            return false;
        }

        $node = $this->registry->resolve($nodeType);

        if (! $node instanceof SupportsTriggerMode) {
            return false;
        }

        return $node->triggerItems([$isReaction ? 'reactions' : 'messages' => [$item]], $nodeConfig) !== [];
    }

    /**
     * A message event as the history call would list it, plus its channel.
     */
    public function itemFor(array $event): array
    {
        if (in_array($event['type'] ?? null, ['reaction_added', 'reaction_removed'], true)) {
            return array_filter([
                'ts' => $event['item']['ts'] ?? null,
                'channel' => $event['item']['channel'] ?? null,
                'emoji' => $event['reaction'] ?? null,
                'user' => $event['user'] ?? null,
                'item_user' => $event['item_user'] ?? null,
                'event_ts' => $event['event_ts'] ?? null,
                'removed' => $event['type'] === 'reaction_removed' ? true : null,
            ], fn (mixed $value): bool => $value !== null);
        }

        return array_filter(
            array_intersect_key($event, array_flip([
                'ts', 'text', 'user', 'channel', 'thread_ts', 'bot_id', 'subtype', 'team', 'files', 'attachments', 'blocks', 'client_msg_id', 'type',
            ])),
            fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * The message timestamp (or, for a reaction, message:emoji:person), as in the poll path.
     */
    public function itemId(array $item): string
    {
        // A reaction is named message, emoji and person, as the poll names it; a removal also by when.
        if (isset($item['emoji'])) {
            return ($item['ts'] ?? '').':'.$item['emoji'].':'.($item['user'] ?? '').(isset($item['removed']) ? ':removed:'.($item['event_ts'] ?? '') : '');
        }

        // A direct message is named by its conversation too, as the poll names it.
        return str_starts_with((string) ($item['channel'] ?? ''), 'D')
            ? $item['channel'].':'.($item['ts'] ?? '')
            : (string) ($item['ts'] ?? '');
    }
}
