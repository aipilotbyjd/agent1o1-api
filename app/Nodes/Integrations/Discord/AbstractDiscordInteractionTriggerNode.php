<?php

namespace App\Nodes\Integrations\Discord;

use App\Contracts\SupportsTriggerMode;
use App\Contracts\Triggers\NeedsPushDelivery;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * A slash command or button press that starts a flow. Discord *posts* an
 * interaction to the bot's Interactions URL and expects an answer within three
 * seconds; it never lists them. So these work through that URL (instant
 * delivery) or not at all, and reading returns nothing.
 *
 * `DiscordSource` receives the interaction, makes it an item
 * (`DiscordInteraction`) and asks `triggerItems()` whether this trigger wants
 * it, so the settings are decided here, in one place. The URL answers Discord
 * with "thinking…" at once; a following "Respond to Slash Command or Button"
 * step supplies the real reply within 15 minutes.
 */
abstract class AbstractDiscordInteractionTriggerNode extends AbstractDiscordNode implements NeedsPushDelivery, SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * The item kind this trigger is for (`DiscordInteraction::item()`).
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
                'guild' => Field::advanced(Field::text('Only in this server', 'A server id. Leave empty for every server the bot is in, and for direct messages.')),
            ],
        ];
    }

    /**
     * Nothing to read: interactions are only ever pushed.
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
     * The settings that narrow which interactions start the flow.
     *
     * @return array<string, array<string, mixed>>
     */
    abstract protected function filterFields(): array;

    /**
     * Whether the item passes this trigger's own settings, beyond its kind and server.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $config
     */
    abstract protected function matches(array $item, array $config): bool;

    public function triggerItems(array $output, array $config): array
    {
        $guild = trim((string) ($config['guild'] ?? ''));

        return array_values(array_filter(
            array_filter((array) ($output['items'] ?? []), 'is_array'),
            fn (array $item): bool => ($item['kind'] ?? null) === $this->kind()
                && ($guild === '' || (string) ($item['guild_id'] ?? '') === $guild)
                && $this->matches($item, $config),
        ));
    }

    /**
     * Discord's own interaction id: sent again on a retry, never reused.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['interaction_id'] ?? '');
    }

    public function triggerPollMinutes(): int
    {
        return 15;
    }
}
