<?php

namespace App\Nodes\Integrations\Monday;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request, error handling, validation and result shaping for the
 * monday.com node family, on its GraphQL API. The token goes in the
 * `Authorization` header as it is. Every call is a POST of a query and its
 * variables (values are always sent as variables, never put in the query text);
 * a refusal comes back as HTTP 200 with `errors`. Boards, items and users are
 * numbers (as text); groups and columns are short ids such as `topics` and
 * `status`; a column's value is written as JSON.
 */
abstract class AbstractMondayNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.monday.com/v2';

    private const string API_VERSION = '2024-10';

    /**
     * The fields every item node asks for.
     */
    protected const string ITEM_FIELDS = 'id name state created_at updated_at url group { id title } board { id } column_values { id text type column { title } }';

    public function category(): string
    {
        return 'monday';
    }

    /**
     * Run a GraphQL query and return its `data`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function monday(Run $run, array $config, string $query, array $variables = []): array
    {
        $response = Http::withHeaders(['Authorization' => $this->resolveAccessToken($run, $config), 'API-Version' => self::API_VERSION])
            ->acceptJson()->timeout(30)
            ->post(self::API_URL, ['query' => $query, 'variables' => (object) $variables]);

        $errors = $response->json('errors');
        $message = $response->json('error_message') ?? (is_array($errors) ? ($errors[0]['message'] ?? null) : null);

        if ($response->failed() || $message !== null) {
            $code = $response->json('error_code') ?? (is_array($errors) ? ($errors[0]['extensions']['code'] ?? null) : null);
            $hint = match (true) {
                $response->status() === 401 || $code === 'UserUnauthorizedException' => ' Reconnect monday.com: the token was refused.',
                $response->status() === 429 || in_array($code, ['ComplexityException', 'RateLimitExceeded'], true) => ' monday.com is limiting the account; try again in a moment.',
                default => '',
            };
            $text = (string) ($message ?? "HTTP {$response->status()}");

            throw new RuntimeException("monday.com error: {$text}".($code ? " ({$code})" : '').(str_ends_with($text, '.') ? '' : '.').$hint);
        }

        return (array) ($response->json('data') ?? []);
    }

    /**
     * A board, item, user or workspace id (a number) from a setting, checked.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function numericId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return preg_match('/^\d+$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a monday.com id (a number): pick one from the list.");
    }

    /**
     * A group or column id from a setting, checked.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function shortId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} is not a monday.com id: pick one from the list.");
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
     * Column values to write, from a JSON object that may arrive as text, as the text monday.com wants.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function columnValues(array $config, string $key = 'column_values', bool $required = true): ?string
    {
        $value = $config[$key] ?? null;
        $values = is_string($value) ? (trim($value) === '' ? null : json_decode($value, true)) : $value;

        if ($values === null) {
            return $required ? throw new InvalidArgumentException('Give the column values as a JSON object, e.g. {"status": {"label": "Done"}, "text": "Hello"}.') : null;
        }

        if (! is_array($values) || $values === [] || array_is_list($values)) {
            throw new InvalidArgumentException('Give the column values as a JSON object, e.g. {"status": {"label": "Done"}, "text": "Hello"}.');
        }

        return (string) json_encode($values);
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
     * Read a board's items page by page, ordered by one of monday.com's built-in columns, newest first.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    protected function readItems(Run $run, array $config, string $board, ?string $group, string $orderBy, int $limit): array
    {
        $params = ['order_by' => [['column_id' => $orderBy, 'direction' => 'desc']]];

        if ($group !== null) {
            $params['rules'] = [['column_id' => 'group', 'compare_value' => [$group], 'operator' => 'any_of']];
        }

        $items = [];
        $cursor = null;

        do {
            $page = (array) data_get($cursor === null
                ? $this->monday($run, $config, 'query ($ids: [ID!], $limit: Int, $params: ItemsQuery) { boards(ids: $ids) { items_page(limit: $limit, query_params: $params) { cursor items { '.self::ITEM_FIELDS.' } } } }', ['ids' => [$board], 'limit' => min(100, $limit), 'params' => $params])
                : $this->monday($run, $config, 'query ($limit: Int, $cursor: String!) { next_items_page(limit: $limit, cursor: $cursor) { cursor items { '.self::ITEM_FIELDS.' } } }', ['limit' => min(100, $limit - count($items)), 'cursor' => $cursor]), $cursor === null ? 'boards.0.items_page' : 'next_items_page', []);
            array_push($items, ...array_values(array_filter((array) ($page['items'] ?? []), 'is_array')));
            $cursor = $page['cursor'] ?? null;
        } while ($cursor !== null && count($items) < $limit);

        return array_slice($items, 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function shapeItem(array $item): array
    {
        $byTitle = [];
        $byId = [];

        foreach (array_filter((array) ($item['column_values'] ?? []), 'is_array') as $column) {
            $title = (string) (data_get($column, 'column.title') ?? $column['id'] ?? '');
            $text = $column['text'] ?? null;
            $byId[(string) ($column['id'] ?? '')] = $text;
            // Two columns can share a title: keep both rather than overwrite.
            $byTitle[array_key_exists($title, $byTitle) ? "{$title} ({$column['id']})" : $title] = $text;
        }

        return [
            'id' => isset($item['id']) ? (string) $item['id'] : null,
            'name' => $item['name'] ?? null,
            'state' => $item['state'] ?? null,
            'created_at' => $item['created_at'] ?? null,
            'updated_at' => $item['updated_at'] ?? null,
            'url' => $item['url'] ?? null,
            'board_id' => isset($item['board']['id']) ? (string) $item['board']['id'] : null,
            'group_id' => data_get($item, 'group.id'),
            'group' => data_get($item, 'group.title'),
            'columns' => (object) $byTitle,
            'columns_by_id' => (object) $byId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function boardField(): array
    {
        return Field::dynamic('Board', 'monday.boards', 'Pick a board.', 'Marketing plan');
    }

    /**
     * @return array<string, mixed>
     */
    protected function groupField(string $title = 'Group'): array
    {
        return Field::dynamic($title, 'monday.groups', 'Pick a group on the board.', 'This week', dependsOn: ['board_id']);
    }
}
