<?php

namespace App\Nodes\Integrations\Trello;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request, error handling, validation and result shaping for the Trello
 * node family. Trello takes the app's API key and the member's token as `key` and
 * `token` in the query of every call; the connection holds both. Ids are 24
 * letters and digits (a board also has an 8-character short link), and are
 * checked before they go in a URL.
 */
abstract class AbstractTrelloNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.trello.com/1';

    public function category(): string
    {
        return 'trello';
    }

    /**
     * Call Trello and return the decoded body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped; booleans are sent as true and false
     * @param  array<string, mixed>|null  $body
     * @return array<int|string, mixed>
     *
     * @throws RuntimeException
     */
    protected function trello(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->trelloResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Trello and return the response as it is, for a node that treats a 404 as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function trelloResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        [$appKey, $token] = $this->login($run, $config);
        $query = array_map(fn (mixed $value): mixed => is_bool($value) ? ($value ? 'true' : 'false') : $value, array_filter($query, fn (mixed $value): bool => $value !== null));
        $options = ['query' => ['key' => $appKey, 'token' => $token, ...$query]];

        if ($body !== null) {
            $options['json'] = array_filter($body, fn (mixed $value): bool => $value !== null);
        }

        return Http::acceptJson()->timeout(30)->send(strtoupper($method), self::API_URL.$path, $options);
    }

    /**
     * The API key and the token of the connection.
     *
     * @param  array<string, mixed>  $config
     * @return array{0: string, 1: string}
     *
     * @throws InvalidArgumentException
     */
    protected function login(Run $run, array $config): array
    {
        $data = $this->resolveCredentialData($run, $config);
        $token = trim((string) ($config['access_token'] ?? $data['api_key'] ?? ''));
        $appKey = trim((string) ($data['app_key'] ?? ''));

        return $token !== '' && $appKey !== '' ? [$appKey, $token] : throw new InvalidArgumentException('Connect Trello: both its API key and its token are needed.');
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = trim((string) ($response->json('message') ?? $response->json('error') ?? $response->body())) ?: "HTTP {$response->status()}";
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Trello: the key or token was refused, or it has no access to this.',
            $response->status() === 429 => ' Trello is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Trello error: '.mb_substr($message, 0, 300).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * An id from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function id(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required: pick one from the list.");
        }

        return preg_match('/^[A-Za-z0-9]+$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} is not a valid Trello id: pick one from the list.");
    }

    /**
     * A list of ids from a list or comma-separated text.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function ids(mixed $value, string $label): array
    {
        $parts = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value);

        return array_values(array_unique(array_map(fn (mixed $part): string => $this->id(['id' => $part], 'id', $label), array_filter((array) $parts, fn (mixed $part): bool => trim((string) $part) !== ''))));
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
     * A date setting as Trello wants it: UTC, ISO 8601; null when empty.
     *
     * @param  array<string, mixed>  $config
     */
    protected function date(array $config, string $key): ?string
    {
        return filled($config[$key] ?? null) ? Carbon::parse((string) $config[$key])->utc()->format('Y-m-d\TH:i:s.v\Z') : null;
    }

    /**
     * How many records a list node reads: its `limit`, between 1 and `$max`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 1000): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * The position setting: top, bottom, or a number.
     *
     * @param  array<string, mixed>  $config
     */
    protected function position(array $config): string|int|float|null
    {
        $pos = $config['position'] ?? null;

        return match (true) {
            in_array($pos, ['top', 'bottom'], true) => $pos,
            is_numeric($pos) => $pos + 0,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    protected function shapeCard(array $card): array
    {
        return [
            'id' => $card['id'] ?? null,
            'name' => $card['name'] ?? null,
            'description' => $card['desc'] ?? null,
            'url' => $card['shortUrl'] ?? $card['url'] ?? null,
            'list_id' => $card['idList'] ?? null,
            'board_id' => $card['idBoard'] ?? null,
            'due' => $card['due'] ?? null,
            'due_complete' => (bool) ($card['dueComplete'] ?? false),
            'archived' => (bool) ($card['closed'] ?? false),
            'labels' => array_values(array_filter(array_map(fn (mixed $label): ?string => is_array($label) ? ($label['name'] ?? $label['color'] ?? null) : null, (array) ($card['labels'] ?? [])))),
            'label_ids' => array_values((array) ($card['idLabels'] ?? [])),
            'member_ids' => array_values((array) ($card['idMembers'] ?? [])),
            'last_activity' => $card['dateLastActivity'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    protected function shapeAction(array $action): array
    {
        $data = (array) ($action['data'] ?? []);

        return [
            'id' => $action['id'] ?? null,
            'type' => $action['type'] ?? null,
            'date' => $action['date'] ?? null,
            'member' => data_get($action, 'memberCreator.fullName'),
            'username' => data_get($action, 'memberCreator.username'),
            'card_id' => data_get($data, 'card.id'),
            'card_name' => data_get($data, 'card.name'),
            'board_id' => data_get($data, 'board.id'),
            'list' => data_get($data, 'listAfter.name') ?? data_get($data, 'list.name'),
            'list_before' => data_get($data, 'listBefore.name'),
            'text' => $data['text'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function boardField(string $title = 'Board'): array
    {
        return Field::dynamic($title, 'trello.boards', 'Pick a board.', 'Marketing');
    }

    /**
     * @return array<string, mixed>
     */
    protected function listField(string $title = 'List'): array
    {
        return Field::dynamic($title, 'trello.lists', 'Pick a list on the board.', 'To do', dependsOn: ['board_id']);
    }
}
