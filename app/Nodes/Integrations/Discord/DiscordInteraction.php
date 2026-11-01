<?php

namespace App\Nodes\Integrations\Discord;

use Illuminate\Support\Carbon;

/**
 * Turns an interaction Discord posts to the bot's Interactions URL (a slash
 * command, a button press) into the flat record a flow receives.
 */
final class DiscordInteraction
{
    public const int TYPE_PING = 1;

    public const int TYPE_COMMAND = 2;

    public const int TYPE_COMPONENT = 3;

    public const int TYPE_AUTOCOMPLETE = 4;

    public const int TYPE_MODAL = 5;

    /**
     * The interaction as a trigger item, or null for a kind no trigger is for.
     *
     * @param  array<string, mixed>  $interaction
     * @return array<string, mixed>|null
     */
    public static function item(array $interaction): ?array
    {
        $type = (int) ($interaction['type'] ?? 0);

        if (! in_array($type, [self::TYPE_COMMAND, self::TYPE_COMPONENT], true)) {
            return null;
        }

        $user = (array) ($interaction['member']['user'] ?? $interaction['user'] ?? []);
        $data = (array) ($interaction['data'] ?? []);
        $id = (string) ($interaction['id'] ?? '');

        $item = [
            'kind' => $type === self::TYPE_COMMAND ? 'slash_command' : 'button_click',
            'interaction_id' => $id,
            'interaction_token' => $interaction['token'] ?? null,
            'application_id' => $interaction['application_id'] ?? null,
            'guild_id' => $interaction['guild_id'] ?? null,
            'channel_id' => $interaction['channel_id'] ?? data_get($interaction, 'channel.id'),
            'user_id' => $user['id'] ?? null,
            'username' => $user['username'] ?? null,
            'display_name' => $interaction['member']['nick'] ?? $user['global_name'] ?? $user['username'] ?? null,
            'role_ids' => array_values(array_map('strval', (array) ($interaction['member']['roles'] ?? []))),
            'locale' => $interaction['locale'] ?? null,
            'created_at' => is_numeric($id) ? Carbon::createFromTimestampMsUTC(((int) $id >> 22) + 1420070400000)->toIso8601String() : null,
        ];

        if ($type === self::TYPE_COMMAND) {
            [$name, $subcommand, $options] = self::command($data);

            return [...$item, 'command' => $name, 'subcommand' => $subcommand, 'options' => $options, 'command_id' => $data['id'] ?? null];
        }

        return [
            ...$item,
            'custom_id' => $data['custom_id'] ?? null,
            // A select menu sends the values chosen; a button sends none.
            'values' => array_values((array) ($data['values'] ?? [])),
            'message_id' => data_get($interaction, 'message.id'),
            'message_content' => data_get($interaction, 'message.content'),
        ];
    }

    /**
     * The command's name, its subcommand and its options as a name => value map.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?string, 1: ?string, 2: array<string, mixed>}
     */
    private static function command(array $data): array
    {
        $name = isset($data['name']) ? strtolower((string) $data['name']) : null;
        $options = (array) ($data['options'] ?? []);
        $subcommand = null;

        // A subcommand (type 1) or a group of them (type 2) wraps the real options.
        while ($options !== [] && in_array($options[0]['type'] ?? null, [1, 2], true)) {
            $subcommand = trim($subcommand.' '.$options[0]['name']);
            $options = (array) ($options[0]['options'] ?? []);
        }

        $values = [];

        foreach ($options as $option) {
            if (is_array($option) && isset($option['name'])) {
                $values[(string) $option['name']] = $option['value'] ?? null;
            }
        }

        return [$name, $subcommand, $values];
    }
}
