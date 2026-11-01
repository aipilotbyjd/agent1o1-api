<?php

namespace App\Nodes\Integrations\Telegram;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request, error handling and result shaping for the Telegram node
 * family. A bot's token (the connected account's one secret) goes in the URL of
 * every Bot API call; Telegram answers `{ok, result}` or `{ok: false,
 * error_code, description}`, with the HTTP status mirroring `error_code`.
 *
 * Every node hands back plain, flattened records (`chat_id`, `from_username`,
 * `date` as ISO text) rather than Telegram's nested objects. A chat is
 * given as its numeric id or, for a public channel or group, `@username`.
 */
abstract class AbstractTelegramNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.telegram.org';

    /**
     * Text longer than this is refused by Telegram (captions: 1024).
     */
    protected const int MAX_TEXT = 4096;

    protected const int MAX_CAPTION = 1024;

    public function category(): string
    {
        return 'telegram';
    }

    /**
     * Call one Bot API method and return its `result`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params  null values are dropped
     *
     * @throws RuntimeException
     */
    protected function call(Run $run, array $config, string $method, array $params = []): mixed
    {
        $params = array_filter($params, fn (mixed $value): bool => $value !== null);

        $url = self::API_URL.'/bot'.$this->resolveAccessToken($run, $config).'/'.$method;
        $request = Http::acceptJson()->asJson()->timeout(30);

        // A method with nothing to say (getMe, getMyCommands) is asked for, not sent an empty body.
        $response = $params === [] ? $request->get($url) : $request->post($url, $params);

        if ($response->json('ok') !== true) {
            $description = (string) ($response->json('description') ?? "HTTP {$response->status()}");
            $wait = $response->json('parameters.retry_after');

            throw new RuntimeException('Telegram error'.($response->json('error_code') ? ' '.$response->json('error_code') : '').": {$description}".($wait ? " (try again in {$wait}s)" : '').'.');
        }

        return $response->json('result');
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
     * The chat to act in: an id (a number, negative for groups) or @username.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function chat(array $config, string $key = 'chat_id'): string|int
    {
        $chat = $this->requiredString($config, $key, 'The chat');

        if (preg_match('/^-?\d+$/', $chat) === 1) {
            return (int) $chat;
        }

        if (preg_match('/^@[A-Za-z][A-Za-z0-9_]{3,}$/', $chat) === 1) {
            return $chat;
        }

        throw new InvalidArgumentException("\"{$chat}\" is not a Telegram chat. Use its numeric id or, for a public channel or group, @username.");
    }

    /**
     * A whole number setting that must be there.
     *
     * @param  array<string, mixed>  $config
     */
    protected function requiredInt(array $config, string $key, string $label): int
    {
        $value = $config[$key] ?? null;

        if (! is_numeric($value) || (int) $value != $value) {
            throw new InvalidArgumentException("{$label} is required and must be a number.");
        }

        return (int) $value;
    }

    /**
     * A text that fits Telegram's limit.
     *
     * @param  array<string, mixed>  $config
     */
    protected function text(array $config, string $key = 'text', string $label = 'The message', int $max = self::MAX_TEXT): string
    {
        $text = (string) ($config[$key] ?? '');

        if (trim($text) === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        if (mb_strlen($text) > $max) {
            throw new InvalidArgumentException("{$label} is over Telegram's limit of {$max} characters.");
        }

        return $text;
    }

    /**
     * An optional caption that fits Telegram's limit.
     *
     * @param  array<string, mixed>  $config
     */
    protected function caption(array $config): ?string
    {
        $caption = (string) ($config['caption'] ?? '');

        if ($caption === '') {
            return null;
        }

        return mb_strlen($caption) <= self::MAX_CAPTION ? $caption : throw new InvalidArgumentException('The caption is over Telegram\'s limit of '.self::MAX_CAPTION.' characters.');
    }

    /**
     * What every "send" method takes besides its content: formatting, sound,
     * reply, topic and buttons.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function sendOptions(array $config, bool $formatting = true): array
    {
        $parseMode = $formatting ? $this->parseMode($config['parse_mode'] ?? null) : null;
        $reply = $config['reply_to_message_id'] ?? null;
        $thread = $config['thread_id'] ?? null;

        return [
            'parse_mode' => $parseMode,
            'disable_notification' => filter_var($config['silent'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'protect_content' => filter_var($config['protect_content'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'reply_parameters' => is_numeric($reply) ? ['message_id' => (int) $reply, 'allow_sending_without_reply' => true] : null,
            'message_thread_id' => is_numeric($thread) ? (int) $thread : null,
            'reply_markup' => $this->replyMarkup($config),
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function parseMode(mixed $value): ?string
    {
        $mode = trim((string) $value);

        if ($mode === '' || strtolower($mode) === 'none') {
            return null;
        }

        foreach (['HTML', 'MarkdownV2', 'Markdown'] as $known) {
            if (strcasecmp($mode, $known) === 0) {
                return $known;
            }
        }

        throw new InvalidArgumentException("\"{$mode}\" is not a Telegram format. Use HTML, MarkdownV2 or plain text.");
    }

    /**
     * Buttons under the message (an inline keyboard) or a reply keyboard.
     *
     * `buttons` is rows of `{text, url}` or `{text, callback_data}`; a flat
     * list of buttons is one row. `keyboard` is rows of labels.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     *
     * @throws InvalidArgumentException
     */
    protected function replyMarkup(array $config): ?array
    {
        $buttons = $this->decoded($config['buttons'] ?? null);

        if ($buttons !== null && $buttons !== []) {
            $rows = $this->isOneRow($buttons) ? [$buttons] : $buttons;

            return ['inline_keyboard' => array_map(fn (mixed $row): array => array_map($this->button(...), array_values((array) $row)), $rows)];
        }

        $keyboard = $this->decoded($config['keyboard'] ?? null);

        if ($keyboard !== null && $keyboard !== []) {
            $rows = $this->isOneRow($keyboard) ? [$keyboard] : $keyboard;

            return [
                'keyboard' => array_map(fn (mixed $row): array => array_map(fn (mixed $label): array => ['text' => (string) (is_array($label) ? ($label['text'] ?? '') : $label)], array_values((array) $row)), $rows),
                'resize_keyboard' => true,
                'one_time_keyboard' => filter_var($config['one_time_keyboard'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return filter_var($config['remove_keyboard'] ?? false, FILTER_VALIDATE_BOOLEAN) ? ['remove_keyboard' => true] : null;
    }

    /**
     * Whether a list is one row of buttons (each a label or a `{text}` object)
     * rather than a list of rows.
     *
     * @param  array<int|string, mixed>  $items
     */
    private function isOneRow(array $items): bool
    {
        return collect($items)->every(fn (mixed $item): bool => ! is_array($item) || array_key_exists('text', $item));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function button(mixed $button): array
    {
        $button = is_array($button) ? $button : ['text' => (string) $button];
        $text = trim((string) ($button['text'] ?? ''));

        if ($text === '') {
            throw new InvalidArgumentException('Every button needs text.');
        }

        if (filled($button['url'] ?? null)) {
            return ['text' => $text, 'url' => (string) $button['url']];
        }

        $data = (string) ($button['callback_data'] ?? $button['data'] ?? $text);

        // Telegram refuses callback data over 64 bytes.
        if (strlen($data) > 64) {
            throw new InvalidArgumentException("The data of the button \"{$text}\" is over Telegram's limit of 64 bytes.");
        }

        return ['text' => $text, 'callback_data' => $data];
    }

    /**
     * A list that may arrive as JSON text from a template.
     *
     * @return array<int|string, mixed>|null
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

            if (! is_array($decoded)) {
                throw new InvalidArgumentException('Expected a list, but got text that is not valid JSON.');
            }

            return $decoded;
        }

        return is_array($value) ? $value : null;
    }

    /**
     * A message as every node returns it.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    protected function shapeMessage(array $message, ?int $fallbackChat = null): array
    {
        $shaped = TelegramUpdate::message($message);
        $shaped['chat_id'] ??= $fallbackChat;

        return $shaped;
    }

    /**
     * What every "send" node adds to its settings: formatting, sound, reply,
     * topic and buttons.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function sendFields(bool $formatting = true, bool $buttons = true): array
    {
        return [
            ...($formatting ? ['parse_mode' => Field::advanced(Field::select('Format', ['none' => 'Plain text', 'HTML' => 'HTML', 'MarkdownV2' => 'MarkdownV2', 'Markdown' => 'Markdown'], 'How to read bold, links and the like in the text.', 'none'))] : []),
            ...($buttons ? [
                'buttons' => Field::advanced(Field::json('Buttons', 'Buttons under the message: rows of {"text": "Open", "url": "https://..."} or {"text": "Approve", "callback_data": "approve:42"}. A button with callback_data starts a Telegram "button pressed" trigger.', 'array')),
                'keyboard' => Field::advanced(Field::json('Reply keyboard', 'Rows of labels that replace the phone keyboard, e.g. [["Yes", "No"]].', 'array')),
                'one_time_keyboard' => Field::advanced(Field::boolean('Hide the reply keyboard after one use')),
                'remove_keyboard' => Field::advanced(Field::boolean('Remove the reply keyboard', 'Takes away a keyboard sent before.')),
            ] : []),
            'reply_to_message_id' => Field::advanced(Field::integer('Reply to message', 'The id of the message to reply to.')),
            'thread_id' => Field::advanced(Field::integer('Topic (thread) id', 'The topic of a group with topics to post in.')),
            'silent' => Field::advanced(Field::boolean('Send silently', 'No notification sound for the people in the chat.')),
            'protect_content' => Field::advanced(Field::boolean('Protect content', 'Stops people forwarding or saving the message.')),
        ];
    }

    /**
     * The setting every chat node starts with.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function chatField(string $description = 'The chat\'s numeric id, or @username for a public channel or group. A person\'s id arrives with each message they send the bot.'): array
    {
        return ['chat_id' => Field::text('Chat', $description, '-1001234567890')];
    }
}
