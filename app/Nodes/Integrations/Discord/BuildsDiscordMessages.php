<?php

namespace App\Nodes\Integrations\Discord;

use App\Nodes\Support\Field;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Reading ids and building the body of a message (text, embed, buttons, who may
 * be pinged), for the nodes that talk to Discord as the bot and the one that
 * posts through a webhook URL.
 */
trait BuildsDiscordMessages
{
    protected const int MAX_CONTENT = 2000;

    protected const array COLORS = ['blurple' => 0x5865F2, 'green' => 0x57F287, 'yellow' => 0xFEE75C, 'fuchsia' => 0xEB459E, 'red' => 0xED4245, 'white' => 0xFFFFFF, 'black' => 0x000000];

    /**
     * A snowflake id from a setting, accepting a pasted mention.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function id(array $config, string $key, string $label, bool $required = true): ?string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            return $required ? throw new InvalidArgumentException("{$label} is required.") : null;
        }

        $value = (string) preg_replace('/^<(?:#|@!?|@&)(\d+)>$/', '$1', $value);

        if (preg_match('/^\d{15,25}$/', $value) !== 1) {
            throw new InvalidArgumentException("\"{$value}\" is not a Discord id for {$label}. Use the id (turn on Developer Mode in Discord, then right-click and Copy ID) or pick it from the list.");
        }

        return $value;
    }

    /**
     * A required text setting.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function requiredString(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return $value !== '' ? $value : throw new InvalidArgumentException("{$label} is required.");
    }

    /**
     * A colour from `#5865F2`, `5865F2`, a number or a name, as Discord's integer.
     *
     * @throws InvalidArgumentException
     */
    protected function color(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $text = strtolower(trim((string) $value));

        if (isset(self::COLORS[$text])) {
            return self::COLORS[$text];
        }

        if (preg_match('/^#?([0-9a-f]{6})$/', $text, $match) === 1) {
            return (int) hexdec($match[1]);
        }

        throw new InvalidArgumentException("\"{$value}\" is not a colour. Use a hex code like #5865F2 or a name such as green or red.");
    }

    /**
     * A list that may arrive as JSON text from a template.
     *
     * @return array<int|string, mixed>|null
     *
     * @throws InvalidArgumentException
     */
    protected function decoded(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }

            // Text that isn't JSON is a comma-separated list, e.g. ids mapped from an earlier step.
            if (! str_starts_with($value, '[') && ! str_starts_with($value, '{')) {
                return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $part): bool => $part !== ''));
            }

            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : throw new InvalidArgumentException('Expected a list, but got text that is not valid JSON.');
        }

        return is_array($value) ? $value : null;
    }

    /**
     * Comma-separated text (or a list) as a list of trimmed, non-empty strings.
     *
     * @return list<string>
     */
    protected function csv(mixed $value): array
    {
        $parts = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn (mixed $part): string => trim((string) $part), $parts), fn (string $part): bool => $part !== ''));
    }

    /**
     * The body of a message a node posts or edits: text, an embed, buttons and who may be pinged.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when there is nothing to send, or it is over Discord's limits
     */
    protected function messageBody(array $config, bool $allowEmpty = false): array
    {
        $content = (string) ($config['content'] ?? '');

        if (mb_strlen($content) > self::MAX_CONTENT) {
            throw new InvalidArgumentException('The message is over Discord\'s limit of '.self::MAX_CONTENT.' characters.');
        }

        $embeds = $this->embeds($config);
        $components = $this->components($config['buttons'] ?? null);

        if (! $allowEmpty && trim($content) === '' && $embeds === []) {
            throw new InvalidArgumentException('The message needs some text or an embed.');
        }

        $flags = 0;
        $flags |= filter_var($config['silent'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 4096 : 0;
        $flags |= filter_var($config['suppress_embeds'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 4 : 0;
        $reply = $this->id($config, 'reply_to_message_id', 'The message to reply to', required: false);

        return [
            'content' => $content !== '' ? $content : null,
            'embeds' => $embeds === [] ? null : $embeds,
            'components' => $components === [] ? null : $components,
            'tts' => filter_var($config['tts'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'flags' => $flags ?: null,
            'allowed_mentions' => $this->allowedMentions($config['pings'] ?? null),
            'message_reference' => $reply === null ? null : ['message_id' => $reply, 'fail_if_not_exists' => false],
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function allowedMentions(mixed $mode): array
    {
        return match ((string) ($mode ?: 'users')) {
            'all' => ['parse' => ['users', 'roles', 'everyone']],
            'none' => ['parse' => []],
            'users' => ['parse' => ['users']],
            default => throw new InvalidArgumentException("\"{$mode}\" is not a pings setting. Use users, all or none."),
        };
    }

    /**
     * The embed a node's `embed_*` settings describe, plus any raw `embeds`.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    protected function embeds(array $config): array
    {
        $embeds = array_values(array_filter((array) ($this->decoded($config['embeds'] ?? null) ?? []), 'is_array'));

        $embed = array_filter([
            'title' => filled($config['embed_title'] ?? null) ? mb_substr((string) $config['embed_title'], 0, 256) : null,
            'description' => filled($config['embed_description'] ?? null) ? mb_substr((string) $config['embed_description'], 0, 4096) : null,
            'url' => filled($config['embed_url'] ?? null) ? (string) $config['embed_url'] : null,
            'color' => $this->color($config['embed_color'] ?? null),
            'image' => filled($config['embed_image_url'] ?? null) ? ['url' => (string) $config['embed_image_url']] : null,
            'thumbnail' => filled($config['embed_thumbnail_url'] ?? null) ? ['url' => (string) $config['embed_thumbnail_url']] : null,
            'author' => filled($config['embed_author'] ?? null) ? ['name' => mb_substr((string) $config['embed_author'], 0, 256)] : null,
            'footer' => filled($config['embed_footer'] ?? null) ? ['text' => mb_substr((string) $config['embed_footer'], 0, 2048)] : null,
            'fields' => $this->embedFields($config['embed_fields'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        if ($embed !== []) {
            array_unshift($embeds, $embed);
        }

        if (count($embeds) > 10) {
            throw new InvalidArgumentException('A message takes at most 10 embeds.');
        }

        return $embeds;
    }

    /**
     * @return list<array{name: string, value: string, inline: bool}>
     *
     * @throws InvalidArgumentException
     */
    private function embedFields(mixed $value): array
    {
        $fields = [];

        foreach ((array) ($this->decoded($value) ?? []) as $field) {
            if (! is_array($field) || trim((string) ($field['name'] ?? '')) === '' || trim((string) ($field['value'] ?? '')) === '') {
                throw new InvalidArgumentException('Every embed field needs a name and a value.');
            }

            $fields[] = ['name' => mb_substr((string) $field['name'], 0, 256), 'value' => mb_substr((string) $field['value'], 0, 1024), 'inline' => filter_var($field['inline'] ?? false, FILTER_VALIDATE_BOOLEAN)];
        }

        if (count($fields) > 25) {
            throw new InvalidArgumentException('An embed takes at most 25 fields.');
        }

        return $fields;
    }

    /**
     * Buttons under a message: a flat list is one row, a list of lists is rows.
     * A button with a `url` is a link; any other needs a `custom_id`, which the
     * "Button clicked" trigger receives (and needs the Interactions URL set up).
     *
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    protected function components(mixed $buttons): array
    {
        $buttons = $this->decoded($buttons);

        if ($buttons === null || $buttons === []) {
            return [];
        }

        $rows = collect($buttons)->every(fn (mixed $item): bool => ! is_array($item) || array_key_exists('label', $item) || array_key_exists('url', $item) || array_key_exists('custom_id', $item)) ? [array_values($buttons)] : array_values($buttons);

        if (count($rows) > 5) {
            throw new InvalidArgumentException('A message takes at most 5 rows of buttons.');
        }

        return array_map(function (mixed $row): array {
            $row = array_values((array) $row);

            if ($row === [] || count($row) > 5) {
                throw new InvalidArgumentException('Each row takes between 1 and 5 buttons.');
            }

            return ['type' => 1, 'components' => array_map($this->button(...), $row)];
        }, $rows);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function button(mixed $button): array
    {
        $button = is_array($button) ? $button : ['label' => (string) $button];
        $label = mb_substr(trim((string) ($button['label'] ?? '')), 0, 80);

        if ($label === '') {
            throw new InvalidArgumentException('Every button needs a label.');
        }

        $emoji = filled($button['emoji'] ?? null) ? ['name' => (string) $button['emoji']] : null;
        $disabled = filter_var($button['disabled'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null;

        if (filled($button['url'] ?? null)) {
            return array_filter(['type' => 2, 'style' => 5, 'label' => $label, 'url' => (string) $button['url'], 'emoji' => $emoji, 'disabled' => $disabled], fn (mixed $value): bool => $value !== null);
        }

        $styles = ['primary' => 1, 'secondary' => 2, 'success' => 3, 'danger' => 4];
        $style = strtolower((string) ($button['style'] ?? 'primary'));
        $customId = (string) ($button['custom_id'] ?? $button['data'] ?? Str::slug($label, '_'));

        if (! isset($styles[$style])) {
            throw new InvalidArgumentException("\"{$style}\" is not a button style. Use primary, secondary, success or danger.");
        }

        if ($customId === '' || strlen($customId) > 100) {
            throw new InvalidArgumentException("The button \"{$label}\" needs a custom_id of up to 100 characters.");
        }

        return array_filter(['type' => 2, 'style' => $styles[$style], 'label' => $label, 'custom_id' => $customId, 'emoji' => $emoji, 'disabled' => $disabled], fn (mixed $value): bool => $value !== null);
    }

    /**
     * The settings that build a message, shared by every node that posts or edits one.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function messageFields(bool $reply = true): array
    {
        return [
            'content' => Field::textarea('Message', 'Up to 2000 characters. Markdown works. Mention someone with <@their-id>, a channel with <#id>.'),
            'embed_title' => Field::advanced(Field::text('Embed title')),
            'embed_description' => Field::advanced(Field::textarea('Embed text', 'Up to 4096 characters.')),
            'embed_color' => Field::advanced(Field::text('Embed colour', 'A hex code like #5865F2 or a name: blurple, green, yellow, red.', '#5865F2')),
            'embed_url' => Field::advanced(Field::text('Embed link', 'Makes the embed title a link.')),
            'embed_image_url' => Field::advanced(Field::text('Embed image', 'A link to a picture shown large.')),
            'embed_thumbnail_url' => Field::advanced(Field::text('Embed thumbnail', 'A link to a small picture shown in the corner.')),
            'embed_author' => Field::advanced(Field::text('Embed author')),
            'embed_footer' => Field::advanced(Field::text('Embed footer')),
            'embed_fields' => Field::advanced(Field::json('Embed fields', 'A list such as [{"name": "Status", "value": "Open", "inline": true}]. Up to 25.', 'array')),
            'embeds' => Field::advanced(Field::json('More embeds (JSON)', 'Raw Discord embed objects, for anything the settings above can\'t say. Up to 10 in all.', 'array')),
            'buttons' => Field::advanced(Field::json('Buttons', 'Up to 5 buttons per row, 5 rows: {"label": "Docs", "url": "https://..."} opens a link; {"label": "Approve", "custom_id": "approve:42", "style": "success"} starts a "Button clicked" trigger (needs the bot\'s Interactions URL).', 'array')),
            'pings' => Field::advanced(Field::select('Who can be pinged', ['users' => 'People only (never @everyone or roles)', 'all' => 'Everyone, roles and people', 'none' => 'Nobody'], 'Controls which mentions in the text actually notify.', 'users')),
            ...($reply ? ['reply_to_message_id' => Field::advanced(Field::text('Reply to message', 'The id of the message to reply to.'))] : []),
            'silent' => Field::advanced(Field::boolean('Send silently', 'No notification for the people in the channel.')),
            'suppress_embeds' => Field::advanced(Field::boolean('Hide link previews')),
        ];
    }
}
