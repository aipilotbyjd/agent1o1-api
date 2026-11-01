<?php

namespace App\Nodes\Integrations\Notion;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request, error handling and result shaping for the Notion node family.
 * Resolves the access token via `ResolvesConnectorCredential`, and speaks the
 * stable 2022-06-28 API version.
 *
 * Notion returns pages with their properties in a deep, typed form. Every node
 * hands back that raw form under `properties` and, next to it, `values`: the
 * same properties flattened to plain values (`Status: "Done"`), which is what a
 * following step usually wants.
 */
abstract class AbstractNotionNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string BASE_URL = 'https://api.notion.com/v1';

    private const string API_VERSION = '2022-06-28';

    /**
     * The most characters Notion takes in one piece of rich text.
     */
    private const int RICH_TEXT_LIMIT = 2000;

    /**
     * The most children Notion takes in one append call.
     */
    protected const int APPEND_LIMIT = 100;

    public function category(): string
    {
        return 'notion';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function get(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'get', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function post(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'post', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function patch(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'patch', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function delete(Run $run, string $endpoint, array $config): array
    {
        return $this->call($run, 'delete', $endpoint, $config, []);
    }

    /**
     * Reads a list the way Notion pages it (a cursor and a `has_more` flag)
     * until `$limit` results are in or the list ends. `$method` is `get` (the
     * cursor goes in the query string) or `post` (in the body).
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    protected function list(Run $run, string $method, string $endpoint, array $config, array $params, int $limit): array
    {
        $results = [];
        $cursor = null;

        do {
            $page = [...$params, 'page_size' => min(100, $limit - count($results))];

            if ($cursor !== null) {
                $page['start_cursor'] = $cursor;
            }

            $body = $method === 'post' ? $this->post($run, $endpoint, $config, $page) : $this->get($run, $endpoint, $config, $page);

            foreach ((array) ($body['results'] ?? []) as $result) {
                if (is_array($result)) {
                    $results[] = $result;
                }
            }

            $cursor = ($body['has_more'] ?? false) === true ? ($body['next_cursor'] ?? null) : null;
        } while ($cursor !== null && count($results) < $limit);

        return array_slice($results, 0, $limit);
    }

    /**
     * An id out of a config field. People paste a page's address as often as
     * its id, so both work: the 32 hex characters at the end of a Notion link
     * (or an id with or without dashes) become the dashed form the API wants.
     * It is put into the request path, so nothing else gets through.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function id(array $config, string $key, string $label): string
    {
        return self::normalizeId((string) ($config[$key] ?? ''), $label);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function normalizeId(string $value, string $label = 'The id'): string
    {
        $value = trim($value);
        // A link carries the id as the last 32 hex characters before any query string.
        $path = (string) preg_replace('/[?#].*$/', '', $value);

        if (preg_match('/([0-9a-f]{32})$/i', str_replace('-', '', $path), $match) !== 1 || preg_match('/^[0-9a-f-]{32,36}$|^https?:\/\//i', $value) !== 1) {
            throw new InvalidArgumentException("Notion: {$label} must be a Notion id or link.");
        }

        return strtolower(preg_replace('/^(.{8})(.{4})(.{4})(.{4})(.{12})$/', '$1-$2-$3-$4-$5', $match[1]));
    }

    /**
     * A JSON field: already decoded by the editor, or text to decode. Empty is
     * an empty array.
     *
     * @param  array<string, mixed>  $config
     * @return array<mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function jsonField(array $config, string $key, string $label): array
    {
        $value = $config[$key] ?? null;

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("Notion: {$label} must be valid JSON.");
        }

        return $decoded;
    }

    /**
     * Adds blocks to a page or block in groups Notion takes.
     *
     * @param  array<string, mixed>  $config
     * @param  list<array<string, mixed>>  $blocks
     */
    protected function appendInChunks(Run $run, string $parentId, array $config, array $blocks, ?string $after = null): void
    {
        foreach (array_chunk($blocks, self::APPEND_LIMIT) as $chunk) {
            $response = $this->patch($run, "/blocks/{$parentId}/children", $config, array_filter(['children' => $chunk, 'after' => $after], fn (mixed $value): bool => $value !== null));
            // Each group goes after the last block of the one before it, keeping the order.
            $created = (array) ($response['results'] ?? []);
            $after = $after === null || $created === [] ? $after : ($created[array_key_last($created)]['id'] ?? $after);
        }
    }

    /**
     * The name of a database's title column (every database has exactly one).
     *
     * @param  array<string, mixed>  $database
     */
    protected function titleColumn(array $database): string
    {
        foreach ((array) ($database['properties'] ?? []) as $name => $property) {
            if (($property['type'] ?? null) === 'title') {
                return (string) $name;
            }
        }

        throw new RuntimeException('Notion: the database has no title column.');
    }

    /**
     * The database a page belongs to, as Notion describes it (its columns
     * decide how plain values are written).
     *
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function databaseOfPage(Run $run, array $page, array $config): array
    {
        $databaseId = $page['parent']['database_id'] ?? null;

        if (! is_string($databaseId)) {
            throw new InvalidArgumentException('Notion: column values can only be set on a database item. Use "Properties (JSON)" for a page.');
        }

        return $this->get($run, "/databases/{$databaseId}", $config);
    }

    /**
     * Plain values by column name, written as Notion properties by each
     * column's type. A choice is its name, several choices or people are comma
     * separated, a date is an ISO date or time.
     *
     * @param  array<string, mixed>  $database
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function propertiesFromValues(array $database, array $values): array
    {
        $columns = (array) ($database['properties'] ?? []);
        $properties = [];

        foreach ($values as $name => $value) {
            $type = $columns[$name]['type'] ?? null;

            if ($type === null) {
                throw new InvalidArgumentException("Notion: the database has no column named \"{$name}\".");
            }

            $text = is_scalar($value) ? trim((string) $value) : '';
            $list = fn (): array => array_values(array_filter(array_map('trim', is_array($value) ? array_map('strval', $value) : explode(',', $text)), fn (string $part): bool => $part !== ''));

            $properties[$name] = match ($type) {
                'title' => ['title' => $this->richText($text)],
                'rich_text' => ['rich_text' => $this->richText($text)],
                'number' => ['number' => $text === '' ? null : (float) $text + 0],
                'checkbox' => ['checkbox' => filter_var($value, FILTER_VALIDATE_BOOLEAN)],
                'select' => ['select' => $text === '' ? null : ['name' => $text]],
                'status' => ['status' => $text === '' ? null : ['name' => $text]],
                'multi_select' => ['multi_select' => array_map(fn (string $option): array => ['name' => $option], $list())],
                'date' => ['date' => $text === '' ? null : ['start' => $text]],
                'url' => ['url' => $text === '' ? null : $text],
                'email' => ['email' => $text === '' ? null : $text],
                'phone_number' => ['phone_number' => $text === '' ? null : $text],
                'people' => ['people' => array_map(fn (string $id): array => ['id' => $id], $list())],
                'relation' => ['relation' => array_map(fn (string $id): array => ['id' => self::normalizeId($id, "A related page in \"{$name}\"")], $list())],
                default => throw new InvalidArgumentException("Notion: the \"{$name}\" column ({$type}) can't be set from a plain value. Use \"Properties (JSON)\"."),
            };
        }

        return $properties;
    }

    /**
     * Reads a database's items, shaped, newest or oldest by the sorts given.
     *
     * @param  array<string, mixed>  $config
     * @param  array<mixed>  $filter
     * @param  list<array<string, mixed>>  $sorts
     * @return list<array<string, mixed>>
     */
    protected function queryDatabase(Run $run, string $databaseId, array $config, array $filter, array $sorts, int $limit): array
    {
        $params = array_filter(['filter' => $filter === [] ? null : $filter, 'sorts' => $sorts === [] ? null : $sorts], fn (mixed $value): bool => $value !== null);

        return array_map($this->shapePage(...), $this->list($run, 'post', "/databases/{$databaseId}/query", $config, $params, $limit));
    }

    /**
     * Searches what the integration can see, shaped, newest edited first.
     *
     * @param  array<string, mixed>  $config
     * @param  'page'|'database'|null  $object
     * @return list<array<string, mixed>>
     */
    protected function searchObjects(Run $run, array $config, ?string $object, string $query, string $direction, int $limit): array
    {
        $params = array_filter([
            'query' => $query === '' ? null : $query,
            'filter' => $object === null ? null : ['property' => 'object', 'value' => $object],
            'sort' => ['direction' => $direction, 'timestamp' => 'last_edited_time'],
        ], fn (mixed $value): bool => $value !== null);

        return array_map(
            fn (array $result): array => ($result['object'] ?? null) === 'database' ? $this->shapeDatabase($result) : $this->shapePage($result),
            $this->list($run, 'post', '/search', $config, $params, $limit),
        );
    }

    /**
     * The items created within the last `$seconds`.
     *
     * Notion's search sorts by last edit, not by creation, so a page edited long
     * after it was made looks new. A trigger for new things only takes those
     * made lately.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected function createdSince(array $items, int $seconds): array
    {
        $cutoff = now()->subSeconds($seconds);

        return array_values(array_filter($items, function (array $item) use ($cutoff): bool {
            $created = $item['created_time'] ?? null;

            return is_string($created) && Carbon::parse($created)->greaterThanOrEqualTo($cutoff);
        }));
    }

    /**
     * Text as Notion rich text, split at the length Notion allows a piece to have.
     *
     * @return list<array<string, mixed>>
     */
    protected function richText(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return array_map(
            fn (string $chunk): array => ['type' => 'text', 'text' => ['content' => $chunk]],
            mb_str_split($text, self::RICH_TEXT_LIMIT),
        );
    }

    /**
     * Blocks of one kind from text: a block for every non-empty line, except
     * code (all the text in one block) and a divider (no text).
     *
     * @return list<array<string, mixed>>
     */
    protected function blocksFromText(string $text, string $type): array
    {
        if ($type === 'divider') {
            return [['object' => 'block', 'type' => 'divider', 'divider' => new \stdClass]];
        }

        $pieces = $type === 'code' ? [$text] : preg_split('/\R/', $text);

        return array_values(array_map(function (string $line) use ($type): array {
            $content = ['rich_text' => $this->richText($line)];

            if ($type === 'to_do') {
                $content['checked'] = false;
            }

            if ($type === 'code') {
                $content['language'] = 'plain text';
            }

            return ['object' => 'block', 'type' => $type, $type => $content];
        }, array_filter((array) $pieces, fn (string $line): bool => trim($line) !== '')));
    }

    /**
     * The block types the text helpers can write.
     *
     * @return array<string, string>
     */
    protected function blockTypeChoices(): array
    {
        return [
            'paragraph' => 'Paragraph',
            'heading_1' => 'Heading 1',
            'heading_2' => 'Heading 2',
            'heading_3' => 'Heading 3',
            'bulleted_list_item' => 'Bulleted list item',
            'numbered_list_item' => 'Numbered list item',
            'to_do' => 'To-do',
            'quote' => 'Quote',
            'callout' => 'Callout',
            'toggle' => 'Toggle',
            'code' => 'Code',
            'divider' => 'Divider',
        ];
    }

    /**
     * A page as the steps after it see it.
     *
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    protected function shapePage(array $page): array
    {
        $properties = (array) ($page['properties'] ?? []);

        return [
            'id' => $page['id'] ?? null,
            'url' => $page['url'] ?? null,
            'title' => $this->pageTitle($properties),
            'created_time' => $page['created_time'] ?? null,
            'last_edited_time' => $page['last_edited_time'] ?? null,
            'created_by' => $page['created_by']['id'] ?? null,
            'last_edited_by' => $page['last_edited_by']['id'] ?? null,
            'archived' => (bool) ($page['archived'] ?? $page['in_trash'] ?? false),
            'parent_type' => $page['parent']['type'] ?? null,
            'parent_id' => $this->parentId((array) ($page['parent'] ?? [])),
            'icon' => $this->iconOf($page['icon'] ?? null),
            'values' => $this->flattenProperties($properties),
            'properties' => $properties,
        ];
    }

    /**
     * A database as the steps after it see it: its columns by name, with type
     * and (for choices) the options a value can take.
     *
     * @param  array<string, mixed>  $database
     * @return array<string, mixed>
     */
    protected function shapeDatabase(array $database): array
    {
        $columns = [];

        foreach ((array) ($database['properties'] ?? []) as $name => $property) {
            $type = (string) ($property['type'] ?? '');
            $choices = (array) ($property[$type]['options'] ?? []);

            $columns[$name] = array_filter([
                'id' => $property['id'] ?? null,
                'type' => $type,
                'options' => $choices === [] ? null : array_values(array_filter(array_map(fn (mixed $option): ?string => $option['name'] ?? null, $choices))),
            ], fn (mixed $value): bool => $value !== null);
        }

        return [
            'id' => $database['id'] ?? null,
            'url' => $database['url'] ?? null,
            'title' => $this->plain((array) ($database['title'] ?? [])),
            'description' => $this->plain((array) ($database['description'] ?? [])),
            'created_time' => $database['created_time'] ?? null,
            'last_edited_time' => $database['last_edited_time'] ?? null,
            'archived' => (bool) ($database['archived'] ?? $database['in_trash'] ?? false),
            'is_inline' => (bool) ($database['is_inline'] ?? false),
            'parent_type' => $database['parent']['type'] ?? null,
            'parent_id' => $this->parentId((array) ($database['parent'] ?? [])),
            'columns' => $columns,
        ];
    }

    /**
     * A block as the steps after it see it: its kind and, for text blocks, the text.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    protected function shapeBlock(array $block): array
    {
        $type = (string) ($block['type'] ?? '');
        $content = (array) ($block[$type] ?? []);

        return array_filter([
            'id' => $block['id'] ?? null,
            'type' => $type,
            'text' => isset($content['rich_text']) ? $this->plain((array) $content['rich_text']) : ($type === 'child_page' || $type === 'child_database' ? (string) ($content['title'] ?? '') : null),
            'checked' => $type === 'to_do' ? (bool) ($content['checked'] ?? false) : null,
            'has_children' => (bool) ($block['has_children'] ?? false),
            'archived' => (bool) ($block['archived'] ?? false),
            'created_time' => $block['created_time'] ?? null,
            'last_edited_time' => $block['last_edited_time'] ?? null,
            'data' => $content,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * A person or bot as the steps after it see it.
     *
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    protected function shapeUser(array $user): array
    {
        return [
            'id' => $user['id'] ?? null,
            'type' => $user['type'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['person']['email'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? null,
        ];
    }

    /**
     * A comment as the steps after it see it.
     *
     * @param  array<string, mixed>  $comment
     * @return array<string, mixed>
     */
    protected function shapeComment(array $comment): array
    {
        return [
            'id' => $comment['id'] ?? null,
            'discussion_id' => $comment['discussion_id'] ?? null,
            'parent_type' => $comment['parent']['type'] ?? null,
            'parent_id' => $this->parentId((array) ($comment['parent'] ?? [])),
            'text' => $this->plain((array) ($comment['rich_text'] ?? [])),
            'created_by' => $comment['created_by']['id'] ?? null,
            'created_time' => $comment['created_time'] ?? null,
            'last_edited_time' => $comment['last_edited_time'] ?? null,
        ];
    }

    /**
     * The text of a rich text array.
     *
     * @param  array<mixed>  $richText
     */
    protected function plain(array $richText): string
    {
        return implode('', array_map(fn (mixed $piece): string => is_array($piece) ? (string) ($piece['plain_text'] ?? '') : '', $richText));
    }

    /**
     * The page's title: the text of its `title` property.
     *
     * @param  array<string, mixed>  $properties
     */
    protected function pageTitle(array $properties): string
    {
        foreach ($properties as $property) {
            if (($property['type'] ?? null) === 'title') {
                return $this->plain((array) ($property['title'] ?? []));
            }
        }

        return '';
    }

    /**
     * Every property as a plain value, by name: text as a string, a choice as
     * its name, several choices or people as a list, a date as `start` (and
     * `end` when it has one), a number or checkbox as itself.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    protected function flattenProperties(array $properties): array
    {
        $values = [];

        foreach ($properties as $name => $property) {
            if (is_array($property)) {
                $values[$name] = $this->flattenValue((string) ($property['type'] ?? ''), $property[$property['type'] ?? ''] ?? null);
            }
        }

        return $values;
    }

    private function flattenValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'title', 'rich_text' => $this->plain((array) $value),
            'select', 'status' => is_array($value) ? ($value['name'] ?? null) : null,
            'multi_select' => array_values(array_map(fn (mixed $option): mixed => $option['name'] ?? null, (array) $value)),
            'people' => array_values(array_map(fn (mixed $person): mixed => $person['name'] ?? $person['id'] ?? null, (array) $value)),
            'relation' => array_values(array_map(fn (mixed $related): mixed => $related['id'] ?? null, (array) $value)),
            'files' => array_values(array_map(fn (mixed $file): mixed => $file['file']['url'] ?? $file['external']['url'] ?? $file['name'] ?? null, (array) $value)),
            'date' => is_array($value) ? array_filter(['start' => $value['start'] ?? null, 'end' => $value['end'] ?? null], fn (mixed $part): bool => $part !== null) : null,
            'formula' => is_array($value) ? $this->flattenValue((string) ($value['type'] ?? ''), $value[$value['type'] ?? ''] ?? null) : null,
            'rollup' => is_array($value) ? $this->flattenRollup($value) : null,
            'unique_id' => is_array($value) ? trim(($value['prefix'] ?? '').'-'.($value['number'] ?? ''), '-') : null,
            'created_by', 'last_edited_by' => is_array($value) ? ($value['name'] ?? $value['id'] ?? null) : null,
            default => is_array($value) ? null : $value,
        };
    }

    /**
     * @param  array<string, mixed>  $rollup
     */
    private function flattenRollup(array $rollup): mixed
    {
        $type = (string) ($rollup['type'] ?? '');

        if ($type === 'array') {
            return array_values(array_map(
                fn (mixed $item): mixed => is_array($item) ? $this->flattenValue((string) ($item['type'] ?? ''), $item[$item['type'] ?? ''] ?? null) : null,
                (array) ($rollup['array'] ?? []),
            ));
        }

        return $this->flattenValue($type, $rollup[$type] ?? null);
    }

    /**
     * @param  array<string, mixed>  $parent
     */
    private function parentId(array $parent): ?string
    {
        $type = (string) ($parent['type'] ?? '');

        return $type === 'workspace' ? null : (isset($parent[$type]) && is_string($parent[$type]) ? $parent[$type] : null);
    }

    private function iconOf(mixed $icon): ?string
    {
        if (! is_array($icon)) {
            return null;
        }

        return $icon['emoji'] ?? $icon['external']['url'] ?? $icon['file']['url'] ?? null;
    }

    /**
     * Notion signals failure with a normal HTTP status and a JSON body of
     * `code` and `message`. `null` params are dropped; an empty body is `{}`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(Run $run, string $method, string $endpoint, array $config, array $params): array
    {
        $request = $this->request($this->resolveAccessToken($run, $config));
        $params = array_filter($params, fn (mixed $value): bool => $value !== null);

        $body = fn (): string => json_encode($params === [] ? new \stdClass : $params, JSON_THROW_ON_ERROR);

        $response = match ($method) {
            'get' => $params === [] ? $request->get(self::BASE_URL.$endpoint) : $request->get(self::BASE_URL.$endpoint, $params),
            'patch' => $request->withBody($body(), 'application/json')->patch(self::BASE_URL.$endpoint),
            'delete' => $request->delete(self::BASE_URL.$endpoint),
            default => $request->withBody($body(), 'application/json')->post(self::BASE_URL.$endpoint),
        };

        if ($response->failed()) {
            $message = $response->json('message') ?? $response->body();

            throw new RuntimeException("Notion API error [{$endpoint}]: {$message}");
        }

        return $response->json() ?? [];
    }

    private function request(string $token): PendingRequest
    {
        return Http::withToken($token)->withHeaders(['Notion-Version' => self::API_VERSION])->acceptJson()->timeout(30);
    }
}
