<?php

namespace App\Nodes\Integrations\Discord;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the
 * Discord node family. The connected account is a bot: its token goes in an
 * `Authorization: Bot …` header. Discord answers a failure with a status and
 * `{code, message}`; a rate limit (429) says how long to wait, and a short wait
 * is honoured once before the step fails.
 *
 * Ids are Discord "snowflakes" (long numbers kept as text: some are larger than
 * a number in JSON safely holds). A field that takes one accepts a mention
 * pasted from Discord (`<#123>`, `<@123>`, `<@&123>`) too.
 *
 * Every node hands back plain, flattened records with the ids next to the
 * names, which is what a following step usually wants.
 */
abstract class AbstractDiscordNode implements DeclaresEffect, NodeContract
{
    use BuildsDiscordMessages;
    use ResolvesConnectorCredential;

    protected const string API_URL = 'https://discord.com/api/v10';

    /**
     * Discord's limits on a message.
     */
    /**
     * The longest wait for a rate limit that is honoured, in seconds.
     */
    private const float MAX_RATE_LIMIT_WAIT = 5.0;

    /**
     * Milliseconds between 1970 and the start of Discord's own epoch (2015).
     */
    private const int DISCORD_EPOCH_MS = 1420070400000;

    /**
     * Embed and button colours Discord users know by name.
     *
     * @var array<string, int>
     */
    public function category(): string
    {
        return 'discord';
    }

    /**
     * Call the Discord API as the bot and return the decoded body (null for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body  null values are dropped, unless `$keepNulls` (a null there resets a setting)
     * @param  array<string, mixed>  $query
     *
     * @throws RuntimeException
     */
    protected function discord(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = [], ?string $reason = null, bool $keepNulls = false): mixed
    {
        $token = $this->resolveAccessToken($run, $config);
        $body = $body === null || $keepNulls ? $body : array_filter($body, fn (mixed $value): bool => $value !== null);
        $query = array_filter($query, fn (mixed $value): bool => $value !== null);

        $response = $this->send($token, $method, $path, $body, $query, $reason);

        if ($response->status() === 429) {
            $wait = (float) ($response->json('retry_after') ?? 1.0);

            if ($wait <= self::MAX_RATE_LIMIT_WAIT) {
                usleep((int) ($wait * 1_000_000));
                $response = $this->send($token, $method, $path, $body, $query, $reason);
            }
        }

        return $this->decode($response);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $query
     */
    private function send(string $token, string $method, string $path, ?array $body, array $query, ?string $reason): Response
    {
        $request = $this->http($token);

        if ($reason !== null && trim($reason) !== '') {
            $request = $request->withHeaders(['X-Audit-Log-Reason' => rawurlencode(mb_substr(trim($reason), 0, 512))]);
        }

        $options = ['query' => $query];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        return $request->send(strtoupper($method), self::API_URL.$path, $options);
    }

    private function http(string $token): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Bot '.$token, 'User-Agent' => 'DiscordBot ('.config('app.url').', 1.0)'])->acceptJson()->timeout(30);
    }

    /**
     * Turn Discord's answer into a value, or an error that says what it said.
     *
     * @throws RuntimeException
     */
    protected function decode(Response $response): mixed
    {
        if ($response->successful()) {
            return $response->status() === 204 || $response->body() === '' ? null : $response->json();
        }

        $message = (string) ($response->json('message') ?? "HTTP {$response->status()}");
        $code = $response->json('code');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect the Discord bot: its token was refused.',
            $response->status() === 403 => ' Give the bot that permission in the server (or the channel).',
            $response->status() === 429 => ' Discord is rate limiting the bot; try again in a moment.',
            default => '',
        };

        throw new RuntimeException("Discord error: {$message}".($code ? " (code {$code})" : '').".{$hint}");
    }

    /**
     * The bot's application id: stored on the connection, or given on the node.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function applicationId(Run $run, array $config): string
    {
        $id = trim((string) ($config['application_id'] ?? ''));

        if ($id === '') {
            try {
                $id = trim((string) ($this->resolveCredentialData($run, $config)['application_id'] ?? ''));
            } catch (RuntimeException) {
                // No account to read it from: the message below says what to add.
            }
        }

        return preg_match('/^\d{15,25}$/', $id) === 1 ? $id : throw new InvalidArgumentException('Add the Application ID to the Discord connection (Developer Portal > General Information).');
    }

    /**
     * How many records a list node reads: its `limit`, between 1 and `$max`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 100): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * The time a snowflake was made, as ISO text.
     */
    protected function snowflakeTime(mixed $id): ?string
    {
        return is_numeric($id) ? Carbon::createFromTimestampMsUTC(((int) $id >> 22) + self::DISCORD_EPOCH_MS)->toIso8601String() : null;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    protected function shapeMessage(array $message, ?string $guildId = null): array
    {
        $channelId = $message['channel_id'] ?? null;
        $guild = $message['guild_id'] ?? $guildId;

        return [
            'id' => $message['id'] ?? null,
            'channel_id' => $channelId,
            'guild_id' => $guild,
            'content' => $message['content'] ?? null,
            'author_id' => data_get($message, 'author.id'),
            'author_username' => data_get($message, 'author.username'),
            'author_name' => data_get($message, 'member.nick') ?? data_get($message, 'author.global_name') ?? data_get($message, 'author.username'),
            'author_is_bot' => data_get($message, 'author.bot', false),
            'author_is_webhook' => isset($message['webhook_id']),
            'created_at' => $message['timestamp'] ?? $this->snowflakeTime($message['id'] ?? null),
            'edited_at' => $message['edited_timestamp'] ?? null,
            'pinned' => $message['pinned'] ?? false,
            'type' => $message['type'] ?? 0,
            'reply_to_id' => data_get($message, 'message_reference.message_id'),
            'mentions' => array_values(array_map(fn (array $user): string => (string) $user['id'], array_filter((array) ($message['mentions'] ?? []), 'is_array'))),
            'mentions_everyone' => $message['mention_everyone'] ?? false,
            'attachments' => array_values(array_map(fn (array $file): array => ['id' => $file['id'] ?? null, 'filename' => $file['filename'] ?? null, 'url' => $file['url'] ?? null, 'content_type' => $file['content_type'] ?? null, 'size' => $file['size'] ?? null], array_filter((array) ($message['attachments'] ?? []), 'is_array'))),
            'embed_count' => count((array) ($message['embeds'] ?? [])),
            'reactions' => array_values(array_map(fn (array $reaction): array => ['emoji' => data_get($reaction, 'emoji.name'), 'count' => $reaction['count'] ?? 0], array_filter((array) ($message['reactions'] ?? []), 'is_array'))),
            'thread_id' => data_get($message, 'thread.id'),
            'url' => $channelId && isset($message['id']) ? 'https://discord.com/channels/'.($guild ?? '@me')."/{$channelId}/{$message['id']}" : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $channel
     * @return array<string, mixed>
     */
    protected function shapeChannel(array $channel): array
    {
        return [
            'id' => $channel['id'] ?? null,
            'guild_id' => $channel['guild_id'] ?? null,
            'name' => $channel['name'] ?? null,
            'type' => $this->channelTypeName($channel['type'] ?? null),
            'type_id' => $channel['type'] ?? null,
            'topic' => $channel['topic'] ?? null,
            'parent_id' => $channel['parent_id'] ?? null,
            'position' => $channel['position'] ?? null,
            'nsfw' => $channel['nsfw'] ?? false,
            'slowmode_seconds' => $channel['rate_limit_per_user'] ?? 0,
            'owner_id' => $channel['owner_id'] ?? null,
            'archived' => data_get($channel, 'thread_metadata.archived'),
            'locked' => data_get($channel, 'thread_metadata.locked'),
            'message_count' => $channel['message_count'] ?? null,
            'created_at' => $this->snowflakeTime($channel['id'] ?? null),
            'url' => isset($channel['id']) ? 'https://discord.com/channels/'.($channel['guild_id'] ?? '@me')."/{$channel['id']}" : null,
        ];
    }

    protected function channelTypeName(mixed $type): ?string
    {
        return match ($type) {
            0 => 'text', 1 => 'dm', 2 => 'voice', 3 => 'group_dm', 4 => 'category', 5 => 'announcement',
            10 => 'announcement_thread', 11 => 'public_thread', 12 => 'private_thread', 13 => 'stage', 15 => 'forum', 16 => 'media',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    protected function shapeUser(array $user): array
    {
        return [
            'id' => $user['id'] ?? null,
            'username' => $user['username'] ?? null,
            'display_name' => $user['global_name'] ?? $user['username'] ?? null,
            'is_bot' => $user['bot'] ?? false,
            'avatar_url' => isset($user['avatar'], $user['id']) ? "https://cdn.discordapp.com/avatars/{$user['id']}/{$user['avatar']}.png" : null,
            'created_at' => $this->snowflakeTime($user['id'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $member
     * @return array<string, mixed>
     */
    protected function shapeMember(array $member): array
    {
        return [
            ...$this->shapeUser((array) ($member['user'] ?? [])),
            'nickname' => $member['nick'] ?? null,
            'name' => $member['nick'] ?? data_get($member, 'user.global_name') ?? data_get($member, 'user.username'),
            'role_ids' => array_values(array_map('strval', (array) ($member['roles'] ?? []))),
            'joined_at' => $member['joined_at'] ?? null,
            'timed_out_until' => $member['communication_disabled_until'] ?? null,
            'pending' => $member['pending'] ?? false,
        ];
    }

    /**
     * @param  array<string, mixed>  $role
     * @return array<string, mixed>
     */
    protected function shapeRole(array $role): array
    {
        return [
            'id' => $role['id'] ?? null,
            'name' => $role['name'] ?? null,
            'color' => isset($role['color']) ? sprintf('#%06X', $role['color']) : null,
            'position' => $role['position'] ?? null,
            'hoisted' => $role['hoist'] ?? false,
            'mentionable' => $role['mentionable'] ?? false,
            'managed' => $role['managed'] ?? false,
            'permissions' => $role['permissions'] ?? null,
        ];
    }

    /**
     * A server picker.
     *
     * @return array<string, mixed>
     */
    protected function guildField(string $description = 'The server (guild). Pick one the bot is in, or map its id.'): array
    {
        return Field::dynamic('Server', 'discord.guilds', $description, 'My community');
    }

    /**
     * A channel picker across the servers the bot is in.
     *
     * @return array<string, mixed>
     */
    protected function channelField(string $title = 'Channel', string $description = 'Pick a channel, or map its id.'): array
    {
        return Field::dynamic($title, 'discord.channels', $description, '#general');
    }
}
