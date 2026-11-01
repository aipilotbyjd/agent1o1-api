<?php

namespace App\Nodes\Integrations\Telegram;

use Illuminate\Support\Carbon;

/**
 * Turns a Telegram message or update into the flat record the nodes and
 * triggers hand to a flow. One place, so a message looks the same whether a
 * node sent it, edited it or a trigger received it.
 */
final class TelegramUpdate
{
    /**
     * The updates the bot's webhook is registered for, which together cover
     * every Telegram trigger.
     *
     * @var list<string>
     */
    public const array ALLOWED_UPDATES = ['message', 'edited_message', 'channel_post', 'edited_channel_post', 'callback_query', 'poll_answer', 'my_chat_member', 'chat_join_request'];

    /**
     * Media a message may carry, in the order they are looked for.
     *
     * @var list<string>
     */
    private const array MEDIA = ['photo', 'document', 'video', 'audio', 'voice', 'video_note', 'animation', 'sticker'];

    /**
     * One update as a trigger item, or null for an update no trigger is for.
     *
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>|null
     */
    public static function item(array $update): ?array
    {
        $base = ['update_id' => $update['update_id'] ?? null];

        foreach (['message' => 'message', 'edited_message' => 'edited_message', 'channel_post' => 'channel_post', 'edited_channel_post' => 'edited_message'] as $field => $kind) {
            if (is_array($update[$field] ?? null)) {
                $message = $update[$field];
                $kind = self::serviceKind($message) ?? $kind;

                return [...$base, 'kind' => $kind, ...self::message($message)];
            }
        }

        if (is_array($update['callback_query'] ?? null)) {
            $query = $update['callback_query'];
            $message = (array) ($query['message'] ?? []);

            return [
                ...$base,
                'kind' => 'callback_query',
                'callback_query_id' => $query['id'] ?? null,
                'data' => $query['data'] ?? null,
                'chat_id' => data_get($message, 'chat.id'),
                'chat_type' => data_get($message, 'chat.type'),
                'message_id' => $message['message_id'] ?? null,
                'message_text' => $message['text'] ?? $message['caption'] ?? null,
                'inline_message_id' => $query['inline_message_id'] ?? null,
                ...self::person((array) ($query['from'] ?? [])),
            ];
        }

        if (is_array($update['poll_answer'] ?? null)) {
            $answer = $update['poll_answer'];

            return [
                ...$base,
                'kind' => 'poll_answer',
                'poll_id' => $answer['poll_id'] ?? null,
                // Zero-based, as Telegram numbers them. Empty when the person withdrew their vote.
                'option_ids' => array_values((array) ($answer['option_ids'] ?? [])),
                'chat_id' => data_get($answer, 'voter_chat.id'),
                ...self::person((array) ($answer['user'] ?? [])),
            ];
        }

        if (is_array($update['my_chat_member'] ?? null)) {
            $change = $update['my_chat_member'];
            $was = (string) data_get($change, 'old_chat_member.status', '');
            $now = (string) data_get($change, 'new_chat_member.status', '');

            return [
                ...$base,
                'kind' => 'my_chat_member',
                'chat_id' => data_get($change, 'chat.id'),
                'chat_type' => data_get($change, 'chat.type'),
                'chat_title' => data_get($change, 'chat.title') ?? data_get($change, 'chat.username'),
                'old_status' => $was,
                'new_status' => $now,
                'added' => ! in_array($now, ['left', 'kicked'], true) && in_array($was, ['left', 'kicked', ''], true),
                'removed' => in_array($now, ['left', 'kicked'], true),
                'date' => self::iso($change['date'] ?? null),
                ...self::person((array) ($change['from'] ?? [])),
            ];
        }

        if (is_array($update['chat_join_request'] ?? null)) {
            $request = $update['chat_join_request'];

            return [
                ...$base,
                'kind' => 'chat_join_request',
                'chat_id' => data_get($request, 'chat.id'),
                'chat_type' => data_get($request, 'chat.type'),
                'chat_title' => data_get($request, 'chat.title') ?? data_get($request, 'chat.username'),
                'bio' => $request['bio'] ?? null,
                'invite_link' => data_get($request, 'invite_link.invite_link'),
                'date' => self::iso($request['date'] ?? null),
                ...self::person((array) ($request['from'] ?? [])),
            ];
        }

        return null;
    }

    /**
     * A message as every node returns it.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    public static function message(array $message): array
    {
        $chat = (array) ($message['chat'] ?? []);
        $username = $chat['username'] ?? null;
        $messageId = $message['message_id'] ?? null;
        $text = $message['text'] ?? null;
        [$command, $arguments] = self::command($text, $message);
        $media = self::media($message);

        return [
            'message_id' => $messageId,
            'chat_id' => $chat['id'] ?? null,
            'chat_type' => $chat['type'] ?? null,
            'chat_title' => $chat['title'] ?? (trim(($chat['first_name'] ?? '').' '.($chat['last_name'] ?? '')) ?: null),
            'chat_username' => $username,
            'thread_id' => $message['message_thread_id'] ?? null,
            ...self::person((array) ($message['from'] ?? $message['sender_chat'] ?? [])),
            'text' => $text,
            'caption' => $message['caption'] ?? null,
            'command' => $command,
            'command_arguments' => $arguments,
            'date' => self::iso($message['date'] ?? null),
            'edit_date' => self::iso($message['edit_date'] ?? null),
            'reply_to_message_id' => data_get($message, 'reply_to_message.message_id'),
            'reply_to_text' => data_get($message, 'reply_to_message.text'),
            'forwarded' => isset($message['forward_origin']),
            ...$media,
            'contact_phone' => data_get($message, 'contact.phone_number'),
            'contact_name' => isset($message['contact']) ? trim((string) data_get($message, 'contact.first_name').' '.(string) data_get($message, 'contact.last_name')) : null,
            'latitude' => data_get($message, 'location.latitude'),
            'longitude' => data_get($message, 'location.longitude'),
            'new_members' => array_values(array_map(fn (array $member): array => self::person($member), array_filter((array) ($message['new_chat_members'] ?? []), 'is_array'))),
            'left_member' => isset($message['left_chat_member']) ? self::person((array) $message['left_chat_member']) : null,
            'link' => $username && $messageId ? "https://t.me/{$username}/{$messageId}" : null,
        ];
    }

    /**
     * A person (or channel) as `from_*` fields.
     *
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    public static function person(array $person): array
    {
        return [
            'from_id' => $person['id'] ?? null,
            'from_username' => $person['username'] ?? null,
            'from_name' => trim((string) ($person['first_name'] ?? $person['title'] ?? '').' '.(string) ($person['last_name'] ?? '')) ?: null,
            'from_language' => $person['language_code'] ?? null,
            'is_bot' => $person['is_bot'] ?? false,
        ];
    }

    /**
     * The bot command a message starts with (`/start` and `/start@MyBot`
     * are both `start`) and what follows it.
     *
     * @param  array<string, mixed>  $message
     * @return array{0: ?string, 1: ?string}
     */
    private static function command(mixed $text, array $message): array
    {
        $isCommand = collect((array) ($message['entities'] ?? []))->contains(fn (array $entity): bool => ($entity['type'] ?? null) === 'bot_command' && ($entity['offset'] ?? 1) === 0);

        if (! is_string($text) || ! $isCommand || preg_match('~^/([A-Za-z0-9_]+)(?:@\w+)?(?:\s+(.*))?$~su', $text, $match) !== 1) {
            return [null, null];
        }

        return [strtolower($match[1]), isset($match[2]) && trim($match[2]) !== '' ? trim($match[2]) : null];
    }

    /**
     * The file a message carries, if any: its kind and the ids to fetch it.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private static function media(array $message): array
    {
        foreach (self::MEDIA as $type) {
            if (! isset($message[$type])) {
                continue;
            }

            // A photo is a list of sizes, smallest first.
            $file = $type === 'photo' ? (array) (collect((array) $message['photo'])->last() ?? []) : (array) $message[$type];

            return [
                'media_type' => $type,
                'file_id' => $file['file_id'] ?? null,
                'file_unique_id' => $file['file_unique_id'] ?? null,
                'file_name' => $file['file_name'] ?? null,
                'file_size' => $file['file_size'] ?? null,
                'mime_type' => $file['mime_type'] ?? null,
            ];
        }

        return ['media_type' => null, 'file_id' => null, 'file_unique_id' => null, 'file_name' => null, 'file_size' => null, 'mime_type' => null];
    }

    /**
     * Someone joining or leaving shows up as a message with no text of its own.
     *
     * @param  array<string, mixed>  $message
     */
    private static function serviceKind(array $message): ?string
    {
        return match (true) {
            ! empty($message['new_chat_members']) => 'member_joined',
            isset($message['left_chat_member']) => 'member_left',
            default => null,
        };
    }

    private static function iso(mixed $timestamp): ?string
    {
        return is_numeric($timestamp) ? Carbon::createFromTimestampUTC((int) $timestamp)->toIso8601String() : null;
    }
}
