<?php

namespace App\Nodes\Integrations\Telegram;

use App\Contracts\SupportsTriggerMode;
use App\Contracts\Triggers\NeedsPushDelivery;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * A Telegram event that starts a flow. Telegram only ever *sends* a bot its
 * updates (to the webhook the app registers for the bot); there is nothing to
 * list afterwards, and polling would fight the webhook for them. So these work
 * through instant delivery or not at all, and reading returns nothing.
 *
 * `TelegramSource` receives the update, makes it an item (`TelegramUpdate`)
 * and asks `triggerItems()` whether this trigger wants it, so the filters in
 * the node's settings are decided here, in one place.
 */
abstract class AbstractTelegramTriggerNode extends AbstractTelegramNode implements NeedsPushDelivery, SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * The item kind this trigger is for (`TelegramUpdate::item()`).
     */
    abstract public function kind(): string;

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
                ...$this->filterFields(),
            ],
        ];
    }

    /**
     * Nothing to read: updates are only ever pushed.
     */
    public function execute(Run $run, array $config, array $context): array
    {
        return ['items' => [], 'count' => 0];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'items';
    }

    /**
     * The settings that narrow which updates start the flow.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function filterFields(): array
    {
        return [
            'chat_id' => Field::text('Only in these chats', 'Chat ids or @usernames, separated by commas. Leave empty for every chat the bot is in.', '-1001234567890'),
        ];
    }

    /**
     * The items of an output that this trigger's settings let through.
     */
    public function triggerItems(array $output, array $config): array
    {
        return array_values(array_filter(
            array_filter((array) ($output['items'] ?? []), 'is_array'),
            fn (array $item): bool => $this->wants($item, $config),
        ));
    }

    /**
     * An update's own number: Telegram sends it again on a retry and never reuses it.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['update_id'] ?? '');
    }

    /**
     * Whether the item is this trigger's kind of event, in a chat it watches.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $config
     */
    protected function wants(array $item, array $config): bool
    {
        return ($item['kind'] ?? null) === $this->kind() && $this->inWatchedChat($item, $config);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $config
     */
    protected function inWatchedChat(array $item, array $config): bool
    {
        $watched = array_values(array_filter(array_map('trim', explode(',', (string) ($config['chat_id'] ?? '')))));

        if ($watched === []) {
            return true;
        }

        $chat = (string) ($item['chat_id'] ?? '');
        $username = filled($item['chat_username'] ?? null) ? '@'.$item['chat_username'] : null;

        foreach ($watched as $candidate) {
            if ($candidate === $chat || ($username !== null && strcasecmp($candidate, $username) === 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Telegram has no listing to poll, so a slow timer costs nothing.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }
}
