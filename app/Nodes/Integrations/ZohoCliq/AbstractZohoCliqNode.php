<?php

namespace App\Nodes\Integrations\ZohoCliq;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use App\Services\Connectors\CrmAccessTokens;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Zoho Cliq
 * node family. Cliq shares the Zoho login of the other Zoho apps (see
 * `CrmAccessTokens`) and is called at the data center's `cliq` address on its v2
 * API. A channel is named by its unique name (lower case, digits, `_` and `-`), a
 * chat by its id, a person by their email address.
 */
abstract class AbstractZohoCliqNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public function category(): string
    {
        return 'zoho_cliq';
    }

    /**
     * Call Zoho Cliq and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function cliq(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->cliqResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function cliqResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $login = app(CrmAccessTokens::class)->zohoApp($this->resolveCredentialData($run, $config), 'cliq');
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$login['token']])->acceptJson()->timeout(30)->send(strtoupper($method), $login['base'].$path, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('message') ?? $response->json('code') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Zoho Cliq: the token was refused or lacks the Cliq scopes.',
            $response->status() === 403 => ' The Zoho Cliq user is not allowed to do that.',
            $response->status() === 429 => ' Zoho Cliq is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Zoho Cliq error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * A channel's unique name from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function channelName(array $config, string $key = 'channel'): string
    {
        $value = strtolower(trim((string) ($config[$key] ?? '')));

        if ($value === '') {
            throw new InvalidArgumentException('The channel is required.');
        }

        return preg_match('/^[a-z0-9_-]{1,100}$/', $value) === 1 ? $value : throw new InvalidArgumentException('The channel must be its unique name (lower case letters, digits, _ and -).');
    }

    /**
     * A chat id from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function chatId(array $config, string $key = 'chat_id'): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('The chat id is required.');
        }

        return preg_match('/^[A-Za-z0-9_-]{3,100}$/', $value) === 1 ? $value : throw new InvalidArgumentException('The chat id may only hold letters, digits, _ and -.');
    }

    /**
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
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 200): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * The chat id behind a channel's unique name.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws RuntimeException
     */
    protected function chatOfChannel(Run $run, array $config, string $name): string
    {
        $channel = $this->cliq($run, $config, 'GET', "/channelsbyname/{$name}");
        $chat = $channel['chat_id'] ?? $channel['channel']['chat_id'] ?? $channel['data']['chat_id'] ?? null;

        return filled($chat) ? (string) $chat : throw new RuntimeException("Zoho Cliq did not show the chat of channel \"{$name}\".");
    }

    /**
     * The latest messages of the channel or chat in the settings, newest first.
     *
     * @param  array<string, mixed>  $config
     * @return array{messages: list<array<string, mixed>>, count: int}
     */
    protected function readMessages(Run $run, array $config): array
    {
        $chat = filled($config['channel'] ?? null) ? $this->chatOfChannel($run, $config, $this->channelName($config)) : $this->chatId($config);
        $messages = array_values(array_filter((array) ($this->cliq($run, $config, 'GET', "/chats/{$chat}/messages", ['limit' => $this->limit($config, 25)])['data'] ?? []), 'is_array'));

        usort($messages, fn (array $a, array $b): int => (int) ($b['time'] ?? 0) <=> (int) ($a['time'] ?? 0));

        return ['messages' => array_map($this->shapeMessage(...), $messages), 'count' => count($messages)];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    protected function shapeMessage(array $message): array
    {
        $time = $message['time'] ?? null;
        $text = $message['content']['text'] ?? $message['text'] ?? null;

        return ['id' => isset($message['id']) ? (string) $message['id'] : null, 'text' => is_string($text) ? $text : null, 'type' => $message['type'] ?? null, 'sender_id' => data_get($message, 'sender.id'), 'sender' => data_get($message, 'sender.name'), 'sent_at' => is_numeric($time) ? gmdate('c', intdiv((int) $time, 1000)) : null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function channelField(string $title = 'Channel'): array
    {
        return Field::dynamic($title, 'zoho_cliq.channels', null, 'general');
    }
}
