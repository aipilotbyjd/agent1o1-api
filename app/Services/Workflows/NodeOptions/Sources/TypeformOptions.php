<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;

/**
 * The account's forms (newest first, searched by title on Typeform's side) and
 * workspaces. An EU account's calls go to the EU host, named on the connection.
 */
class TypeformOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 50;

    public function sources(): array
    {
        return ['typeform.forms', 'typeform.workspaces'];
    }

    protected function appName(): string
    {
        return 'Typeform';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $host = strtolower((string) ($query->credentialData['region'] ?? '')) === 'eu' ? 'https://api.eu.typeform.com' : 'https://api.typeform.com';
        $page = $this->intCursor($query);

        if ($source === 'typeform.workspaces') {
            $body = $this->getJson($query, "{$host}/workspaces", ['page' => $page, 'page_size' => self::PAGE_SIZE, 'search' => $query->search]);

            return new NodeOptionsPage(
                collect($body['items'] ?? [])->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']))
                    ->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], $item['name'] ?? null, isset($item['forms']['count']) ? $item['forms']['count'].' forms' : null)),
                $page < (int) ($body['page_count'] ?? 1) ? $page + 1 : null,
            );
        }

        $body = $this->getJson($query, "{$host}/forms", ['page' => $page, 'page_size' => self::PAGE_SIZE, 'search' => $query->search, 'sort_by' => 'last_updated_at', 'order_by' => 'desc']);

        return new NodeOptionsPage(
            collect($body['items'] ?? [])->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']))
                ->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], $item['title'] ?? null, $item['id'])),
            $page < (int) ($body['page_count'] ?? 1) ? $page + 1 : null,
        );
    }
}
