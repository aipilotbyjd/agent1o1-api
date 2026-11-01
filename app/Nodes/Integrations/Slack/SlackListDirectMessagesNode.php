<?php

namespace App\Nodes\Integrations\Slack;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * Recent direct messages people sent to the connected app. As a flow trigger it
 * starts a run for each new one, instantly through Slack's Events API.
 *
 * Reading costs a call for the list of conversations and one for each, so the
 * timer path looks at a bounded number of them over a short window; instant
 * delivery is what keeps it prompt.
 */
class SlackListDirectMessagesNode extends AbstractSlackNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * Messages that are channel bookkeeping rather than something a person wrote.
     *
     * @var list<string>
     */
    private const array EVENT_SUBTYPES = ['message_changed', 'message_deleted', 'message_replied', 'pinned_item', 'unpinned_item'];

    public function type(): string
    {
        return 'slack_list_direct_messages';
    }

    public function name(): string
    {
        return 'Slack: List Direct Messages';
    }

    public function description(): string
    {
        return 'Lists recent direct messages people sent to the connected app.';
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
                'within_hours' => Field::integer('Messages from the last (hours)', 'How far back to look.', 24, 1, 168),
                'conversations' => Field::advanced(Field::integer('Conversations to check', 'How many direct message conversations to read.', 25, 1, 100)),
                'ignore_bot_messages' => Field::boolean('Ignore bot messages', 'When used as a trigger, skip messages from bots and integrations.', true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $conversations = $this->get($run, 'conversations.list', $config, [
            'types' => 'im',
            'exclude_archived' => 'true',
            'limit' => max(1, min(100, (int) ($config['conversations'] ?? 25))),
        ]);

        $oldest = now()->subHours((int) ($config['within_hours'] ?? 24))->getTimestamp();
        $messages = [];

        foreach ((array) ($conversations['channels'] ?? []) as $conversation) {
            if (! is_array($conversation) || ! isset($conversation['id'])) {
                continue;
            }

            $history = $this->get($run, 'conversations.history', $config, ['channel' => $conversation['id'], 'oldest' => $oldest, 'limit' => 20]);

            foreach ((array) ($history['messages'] ?? []) as $message) {
                if (is_array($message)) {
                    $messages[] = [...$message, 'channel' => $conversation['id'], 'dm_user' => $conversation['user'] ?? null];
                }
            }
        }

        usort($messages, fn (array $a, array $b): int => (float) ($b['ts'] ?? 0) <=> (float) ($a['ts'] ?? 0));

        return ['messages' => $messages];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'messages';
    }

    /**
     * What a person wrote: no bookkeeping events, and no bots unless asked.
     */
    public function triggerItems(array $output, array $config): array
    {
        $ignoreBots = ($config['ignore_bot_messages'] ?? true) !== false;

        return array_values(array_filter(
            (array) ($output['messages'] ?? []),
            fn (mixed $message): bool => is_array($message)
                && ! in_array($message['subtype'] ?? null, self::EVENT_SUBTYPES, true)
                && ! ($ignoreBots && (isset($message['bot_id']) || ($message['subtype'] ?? null) === 'bot_message')),
        ));
    }

    /**
     * The conversation and the message time: a time is only unique within a conversation.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['channel'] ?? '').':'.($item['ts'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Slack: Direct message';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone sends the connected app a direct message.';
    }

    public function triggerPollMinutes(): int
    {
        return 5;
    }

    /**
     * Newest first.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
