<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The account's boards, and the groups and columns of the chosen board.
 * monday.com is GraphQL on one URL; it can't search, so a search filters what
 * came back, and boards page by number.
 */
class MondayOptions extends HttpOptionsSource
{
    private const string API = 'https://api.monday.com/v2';

    private const int PAGE_SIZE = 100;

    public function sources(): array
    {
        return ['monday.boards', 'monday.groups', 'monday.columns'];
    }

    protected function appName(): string
    {
        return 'monday.com';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['Authorization' => (string) $query->token, 'API-Version' => '2024-10'])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        if ($source === 'monday.boards') {
            $page = $this->intCursor($query, 1);
            $boards = $this->run($query, 'query ($limit: Int, $page: Int) { boards(limit: $limit, page: $page, state: active, order_by: used_at) { id name } }', ['limit' => self::PAGE_SIZE, 'page' => $page])['boards'] ?? [];
            $options = collect($boards)->filter(fn (mixed $board): bool => is_array($board) && isset($board['id']) && $query->matches((string) ($board['name'] ?? '')));

            return new NodeOptionsPage($options->map(fn (array $board): NodeOption => new NodeOption((string) $board['id'], (string) ($board['name'] ?? $board['id']))), count($boards) === self::PAGE_SIZE ? (string) ($page + 1) : null);
        }

        $board = trim((string) $query->configString('board_id'));

        if (preg_match('/^\d+$/', $board) !== 1) {
            return new NodeOptionsPage(collect());
        }

        $columns = $source === 'monday.columns';
        $found = $this->run($query, 'query ($ids: [ID!]) { boards(ids: $ids) { groups { id title } columns { id title type } } }', ['ids' => [$board]]);
        $items = collect($found['boards'][0][$columns ? 'columns' : 'groups'] ?? [])->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && $query->matches((string) ($item['title'] ?? '')));

        return new NodeOptionsPage($items->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], (string) ($item['title'] ?? $item['id']), $columns ? ($item['type'] ?? null) : null)));
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function run(NodeOptionsQuery $query, string $graphql, array $variables): array
    {
        try {
            $response = $this->http($query)->post(self::API, ['query' => $graphql, 'variables' => (object) $variables]);
        } catch (ConnectionException) {
            throw new ConnectorException("Couldn't reach {$this->appName()}. Try again in a moment.");
        }

        $this->ensureSuccessful($response, self::API);

        // monday.com answers a refusal (a bad token, a deleted board) with HTTP 200 and `errors`.
        if ($response->json('errors') !== null || $response->json('error_message') !== null) {
            throw new ConnectorException("Couldn't load options from {$this->appName()}: ".($response->json('error_message') ?? $response->json('errors.0.message')));
        }

        return (array) ($response->json('data') ?? []);
    }
}
