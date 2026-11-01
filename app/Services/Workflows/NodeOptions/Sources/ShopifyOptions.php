<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Nodes\Integrations\Shopify\AbstractShopifyNode;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The store's locations, products and customers. Products and customers are listed
 * newest first and followed by Shopify's `page_info` cursor (from its `Link`
 * header); a customer search is asked of Shopify, which finds words in names,
 * emails and tags. The store is the one named on the connection, and only a
 * `*.myshopify.com` address is ever called.
 */
class ShopifyOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 50;

    public function sources(): array
    {
        return ['shopify.locations', 'shopify.products', 'shopify.customers'];
    }

    protected function appName(): string
    {
        return 'Shopify';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['X-Shopify-Access-Token' => (string) $query->token])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $shop = AbstractShopifyNode::normalizeShop((string) ($query->credentialData['shop'] ?? ''))
            ?? throw new ConnectorException('Add the store address (your-store.myshopify.com) to the Shopify connection to load this list.');
        $base = "https://{$shop}/admin/api/".AbstractShopifyNode::API_VERSION;
        $resource = substr($source, strlen('shopify.'));

        if ($resource === 'locations') {
            $body = $this->getJson($query, "{$base}/locations.json");

            return new NodeOptionsPage(
                collect($body['locations'] ?? [])->filter(fn (mixed $row): bool => is_array($row) && isset($row['id']) && ($row['active'] ?? true) && $query->matches((string) ($row['name'] ?? '')))
                    ->map(fn (array $row): NodeOption => new NodeOption((string) $row['id'], (string) ($row['name'] ?? $row['id']), collect([$row['city'] ?? null, $row['country_name'] ?? null])->filter()->implode(', ') ?: null)),
            );
        }

        $searching = $resource === 'customers' && $query->search !== null && $query->cursor === null;
        $params = $query->cursor !== null
            ? ['page_info' => $this->safeCursor($query->cursor), 'limit' => self::PAGE_SIZE]
            : ($searching ? ['query' => $query->search, 'limit' => self::PAGE_SIZE] : ['limit' => self::PAGE_SIZE, 'fields' => $resource === 'products' ? 'id,title,variants' : 'id,first_name,last_name,email']);

        try {
            $response = $this->http($query)->get("{$base}/{$resource}".($searching ? '/search' : '').'.json', array_filter($params, fn (mixed $value): bool => $value !== null));
        } catch (ConnectionException) {
            throw new ConnectorException("Couldn't reach {$this->appName()}. Try again in a moment.");
        }

        $this->ensureSuccessful($response, "{$base}/{$resource}.json");

        $rows = collect((array) $response->json($resource))->filter(fn (mixed $row): bool => is_array($row) && isset($row['id']));
        $options = $resource === 'products'
            ? $rows->filter(fn (array $row): bool => $query->matches((string) ($row['title'] ?? '')))->map(fn (array $row): NodeOption => new NodeOption((string) $row['id'], (string) ($row['title'] ?? $row['id']), (string) $row['id']))
            : $rows->map(fn (array $row): NodeOption => $this->customer($row));

        return new NodeOptionsPage($options, AbstractShopifyNode::nextCursor($response->header('Link')));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function customer(array $row): NodeOption
    {
        $name = trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''));

        return new NodeOption((string) $row['id'], $name !== '' ? $name : (string) ($row['email'] ?? $row['id']), $row['email'] ?? null);
    }

    /**
     * A cursor is the `page_info` we handed out: anything else is not passed on.
     */
    private function safeCursor(string $cursor): ?string
    {
        return preg_match('/^[A-Za-z0-9_\-=.%]{1,2000}$/', $cursor) === 1 ? $cursor : null;
    }
}
