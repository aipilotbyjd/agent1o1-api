<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Nodes\Integrations\Notion\AbstractNotionNode;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Validation\ValidationException;

/**
 * The pages and databases the connection was shared with, the people of the
 * workspace, and a database's columns. Notion pages its lists by cursor, and
 * searches by title itself.
 */
class NotionOptions extends HttpOptionsSource
{
    private const string BASE_URL = 'https://api.notion.com/v1';

    private const int PAGE_SIZE = 50;

    public function sources(): array
    {
        return ['notion.pages', 'notion.databases', 'notion.users', 'notion.properties'];
    }

    protected function appName(): string
    {
        return 'Notion';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return parent::http($query)->withHeaders(['Notion-Version' => '2022-06-28']);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return match ($source) {
            'notion.pages' => $this->search($query, 'page'),
            'notion.databases' => $this->search($query, 'database'),
            'notion.users' => $this->users($query),
            default => $this->properties($query),
        };
    }

    /**
     * Pages or databases, most recently edited first.
     */
    private function search(NodeOptionsQuery $query, string $object): NodeOptionsPage
    {
        $body = $this->postJson($query, '/search', array_filter([
            'query' => $query->search,
            'filter' => ['property' => 'object', 'value' => $object],
            'sort' => ['direction' => 'descending', 'timestamp' => 'last_edited_time'],
            'page_size' => self::PAGE_SIZE,
            'start_cursor' => $query->cursor,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));

        $options = collect((array) ($body['results'] ?? []))
            ->filter(fn (mixed $result): bool => is_array($result) && isset($result['id']))
            ->map(function (array $result): NodeOption {
                $title = $object === 'database'
                    ? implode('', array_map(fn (mixed $piece): string => (string) ($piece['plain_text'] ?? ''), (array) ($result['title'] ?? [])))
                    : $this->pageTitle((array) ($result['properties'] ?? []));
                $icon = $result['icon']['emoji'] ?? null;

                return new NodeOption(
                    (string) $result['id'],
                    trim(($icon !== null ? "{$icon} " : '').($title !== '' ? $title : 'Untitled')),
                    isset($result['last_edited_time']) ? 'Edited '.substr((string) $result['last_edited_time'], 0, 10) : null,
                );
            });

        return new NodeOptionsPage($options, ($body['has_more'] ?? false) === true ? ($body['next_cursor'] ?? null) : null);
    }

    private function users(NodeOptionsQuery $query): NodeOptionsPage
    {
        $body = $this->getJson($query, self::BASE_URL.'/users', ['page_size' => self::PAGE_SIZE, 'start_cursor' => $query->cursor]);

        $options = collect((array) ($body['results'] ?? []))
            ->filter(fn (mixed $user): bool => is_array($user) && isset($user['id']) && ($user['type'] ?? '') === 'person' && $query->matches((string) ($user['name'] ?? '')))
            ->map(fn (array $user): NodeOption => new NodeOption((string) $user['id'], (string) ($user['name'] ?? $user['id']), $user['person']['email'] ?? null));

        return new NodeOptionsPage($options, ($body['has_more'] ?? false) === true ? ($body['next_cursor'] ?? null) : null);
    }

    /**
     * The columns of the node's `database` field.
     *
     * @throws ValidationException
     */
    private function properties(NodeOptionsQuery $query): NodeOptionsPage
    {
        $database = trim((string) $query->configString('database'));

        try {
            $id = AbstractNotionNode::normalizeId($database, 'Database');
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['config.database' => 'Pick a database first.']);
        }

        $body = $this->getJson($query, self::BASE_URL."/databases/{$id}");

        $options = collect((array) ($body['properties'] ?? []))
            ->filter(fn (mixed $property, string|int $name): bool => is_array($property) && $query->matches((string) $name))
            ->map(fn (array $property, string|int $name): NodeOption => new NodeOption((string) $name, (string) $name, str_replace('_', ' ', (string) ($property['type'] ?? ''))))
            ->values();

        return new NodeOptionsPage($options);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function pageTitle(array $properties): string
    {
        foreach ($properties as $property) {
            if (($property['type'] ?? null) === 'title') {
                return implode('', array_map(fn (mixed $piece): string => (string) ($piece['plain_text'] ?? ''), (array) ($property['title'] ?? [])));
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<mixed>
     *
     * @throws ConnectorException
     */
    private function postJson(NodeOptionsQuery $query, string $path, array $body): array
    {
        try {
            $response = $this->http($query)->withBody(json_encode($body, JSON_THROW_ON_ERROR), 'application/json')->post(self::BASE_URL.$path);
        } catch (ConnectionException) {
            throw new ConnectorException("Couldn't reach {$this->appName()}. Try again in a moment.");
        }

        $this->ensureSuccessful($response, $path);

        return (array) $response->json();
    }
}
