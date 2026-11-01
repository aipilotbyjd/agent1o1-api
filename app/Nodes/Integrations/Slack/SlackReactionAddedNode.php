<?php

namespace App\Nodes\Integrations\Slack;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * Reactions people have added to recent messages in a channel. As a flow
 * trigger it starts a run for each new reaction, instantly through Slack's
 * Events API and otherwise by checking on a timer.
 *
 * One item per message, emoji and person, so push and poll name the same
 * reaction the same way (`ts:emoji:user`).
 */
class SlackReactionAddedNode extends AbstractSlackNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'slack_reaction_added';
    }

    public function name(): string
    {
        return 'Slack: Reactions';
    }

    public function description(): string
    {
        return 'Lists the reactions people added to recent messages in a Slack channel.';
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
                'emoji' => Field::list('Emoji', 'Only these reactions, by name without colons (white_check_mark, eyes). Leave empty for any reaction.', 'white_check_mark'),
                'limit' => Field::integer('Messages to look at', 'How many recent messages to check for reactions.', 100, 1, 1000),
            ],
        ];
    }

    /**
     * One entry per reaction a person added to a recent message.
     */
    public function execute(Run $run, array $config, array $context): array
    {
        $history = $this->get($run, 'conversations.history', $config, array_filter([
            'channel' => $config['channel'],
            'limit' => $config['limit'] ?? null,
        ], fn ($value) => $value !== null));

        $reactions = [];

        foreach ((array) ($history['messages'] ?? []) as $message) {
            foreach ((array) ($message['reactions'] ?? []) as $reaction) {
                foreach ((array) ($reaction['users'] ?? []) as $user) {
                    $reactions[] = array_filter([
                        'ts' => $message['ts'] ?? null,
                        'channel' => $config['channel'],
                        'emoji' => $reaction['name'] ?? null,
                        'user' => $user,
                        'text' => $message['text'] ?? null,
                        'thread_ts' => $message['thread_ts'] ?? null,
                    ], fn ($value) => $value !== null);
                }
            }
        }

        return ['reactions' => $reactions];
    }

    /**
     * The reactions, narrowed to the chosen emoji when there are any.
     */
    public function triggerItems(array $output, array $config): array
    {
        $wanted = array_values(array_filter(array_map(
            fn (mixed $name): string => trim((string) $name, ": \t\n\r"),
            (array) ($config['emoji'] ?? []),
        )));

        return array_values(array_filter(
            (array) ($output['reactions'] ?? []),
            fn (mixed $item): bool => is_array($item)
                && filled($item['emoji'] ?? null)
                && ($wanted === [] || in_array($item['emoji'], $wanted, true)),
        ));
    }

    protected function triggerItemsPath(): ?string
    {
        return 'reactions';
    }

    public function triggerName(): string
    {
        return 'Slack: Reaction added';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone reacts to a message in the channel.';
    }

    public function triggerPollMinutes(): int
    {
        return 1;
    }

    /**
     * Wider than the node's default so a burst is not cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => max((int) ($config['limit'] ?? 0), 200)];
    }

    /**
     * Messages come newest first.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * Message, emoji and person: the same name push gives the reaction.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['ts'] ?? '').':'.($item['emoji'] ?? '').':'.($item['user'] ?? '');
    }
}
