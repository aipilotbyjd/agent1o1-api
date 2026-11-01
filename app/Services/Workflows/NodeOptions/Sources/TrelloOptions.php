<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The member's boards, and the lists, labels and members of the chosen board.
 * Trello takes the key and token in the query and can't search, so a search
 * filters what came back.
 */
class TrelloOptions extends HttpOptionsSource
{
    private const string API = 'https://api.trello.com/1';

    public function sources(): array
    {
        return ['trello.boards', 'trello.lists', 'trello.labels', 'trello.members'];
    }

    protected function appName(): string
    {
        return 'Trello';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withQueryParameters(['key' => (string) ($query->credentialData['app_key'] ?? ''), 'token' => (string) $query->token])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        if ($source === 'trello.boards') {
            return $this->page($query, '/members/me/boards', ['filter' => 'open', 'fields' => 'id,name'], 'name');
        }

        $board = trim((string) $query->configString('board_id'));

        if (preg_match('/^[A-Za-z0-9]+$/', $board) !== 1) {
            return new NodeOptionsPage(collect());
        }

        return match ($source) {
            'trello.lists' => $this->page($query, "/boards/{$board}/lists", ['filter' => 'open', 'fields' => 'id,name'], 'name'),
            'trello.labels' => $this->page($query, "/boards/{$board}/labels", ['fields' => 'id,name,color', 'limit' => 1000], 'name', 'color'),
            default => $this->page($query, "/boards/{$board}/members", ['fields' => 'id,fullName,username'], 'fullName', 'username'),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function page(NodeOptionsQuery $query, string $path, array $params, string $label, ?string $detail = null): NodeOptionsPage
    {
        $items = collect($this->getJson($query, self::API.$path, $params))
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && $query->matches((string) ($item[$label] ?? ''), (string) ($detail !== null ? ($item[$detail] ?? '') : '')));

        return new NodeOptionsPage($items->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], (string) (($item[$label] ?? '') !== '' ? $item[$label] : ($item[$detail] ?? $item['id'])), $detail !== null && ($item[$detail] ?? '') !== '' && ($item[$label] ?? '') !== '' ? (string) $item[$detail] : null)));
    }
}
