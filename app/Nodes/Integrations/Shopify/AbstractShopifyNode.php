<?php

namespace App\Nodes\Integrations\Shopify;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Shopify
 * node family. The connection is a store's Admin API access token (a custom app's
 * `shpat_` token) and its `*.myshopify.com` address; the app's API secret key is
 * only needed for instant triggers.
 *
 * Calls go to the REST Admin API at a pinned version, so a Shopify release can't
 * change what a node returns. Lists follow Shopify's `Link: rel="next"` header
 * (a `page_info` cursor); ids are checked to be numbers before they go into a path.
 */
abstract class AbstractShopifyNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    /**
     * The Admin API version every call is made with.
     */
    public const string API_VERSION = '2026-04';

    /**
     * Records Shopify returns in one page, at most.
     */
    protected const int PAGE = 250;

    public function category(): string
    {
        return 'shopify';
    }

    /**
     * The store's `name.myshopify.com` from what was typed (a name, an address or
     * a link to the admin), or null if it isn't one. Only that host is ever called,
     * so a connection can't point a node at another server.
     */
    public static function normalizeShop(string $value): ?string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('~^https?://~', '', $value) ?? $value;
        $value = explode('/', $value)[0];

        if ($value !== '' && ! str_contains($value, '.')) {
            $value .= '.myshopify.com';
        }

        return preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', $value) === 1 ? $value : null;
    }

    /**
     * The connected store.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function shop(Run $run, array $config): string
    {
        $shop = self::normalizeShop((string) ($this->resolveCredentialData($run, $config)['shop'] ?? ''));

        return $shop ?? throw new InvalidArgumentException('The Shopify connection needs the store address, like your-store.myshopify.com.');
    }

    /**
     * Call Shopify and return the response.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body  sent as JSON
     * @param  array<string, mixed>  $query  null values are dropped
     *
     * @throws RuntimeException
     */
    protected function shopifyResponse(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = []): Response
    {
        $request = Http::withHeaders(['X-Shopify-Access-Token' => $this->resolveAccessToken($run, $config)])->acceptJson()->timeout(30);
        $options = ['query' => $this->encode($query)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $this->dropNulls($body);
        }

        $response = $request->send(strtoupper($method), 'https://'.$this->shop($run, $config).'/admin/api/'.self::API_VERSION.$path, $options);

        if (! $response->successful()) {
            $this->fail($response);
        }

        return $response;
    }

    /**
     * Call Shopify and return the decoded body (empty for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function shopify(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = []): array
    {
        return (array) $this->shopifyResponse($run, $config, $method, $path, $body, $query)->json();
    }

    /**
     * @throws RuntimeException
     */
    protected function fail(Response $response): never
    {
        $errors = $response->json('errors');
        $message = match (true) {
            is_string($errors) => $errors,
            is_array($errors) => collect($errors)->map(fn (mixed $messages, mixed $field): string => (is_string($field) ? "{$field} " : '').implode(', ', (array) $messages))->implode('; '),
            default => "HTTP {$response->status()}",
        };
        $hint = match ($response->status()) {
            401 => ' Reconnect Shopify: the access token was refused.',
            402 => ' The store\'s plan is unpaid or frozen.',
            403 => ' The app needs more access scopes (for example read_orders, write_products, read_customers).',
            423 => ' The store is locked.',
            429 => ' Shopify is rate limiting the store; try again in a moment.',
            default => '',
        };

        throw new RuntimeException("Shopify error: {$message}.{$hint}");
    }

    /**
     * Shopify's wire format: no nulls, and `true`/`false` as JSON booleans (kept).
     *
     * @param  array<int|string, mixed>  $values
     * @return array<int|string, mixed>
     */
    protected function encode(array $values): array
    {
        $encoded = [];

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $encoded[$key] = is_array($value) ? $this->encode($value) : (is_bool($value) ? ($value ? 'true' : 'false') : $value);
        }

        return $encoded;
    }

    /**
     * The same, for a JSON body, where booleans stay booleans.
     *
     * @param  array<int|string, mixed>  $values
     * @return array<int|string, mixed>
     */
    protected function dropNulls(array $values): array
    {
        $kept = [];

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $kept[$key] = is_array($value) ? $this->dropNulls($value) : $value;
        }

        return $kept;
    }

    /**
     * Read every page of a list until `$limit` records are in, following the
     * cursor in Shopify's `Link` header.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array{items: list<array<string, mixed>>, has_more: bool}
     */
    protected function listAll(Run $run, array $config, string $path, string $key, array $params, int $limit): array
    {
        $items = [];
        $cursor = null;

        do {
            $query = $cursor === null
                ? [...$params, 'limit' => min(self::PAGE, $limit - count($items))]
                : ['page_info' => $cursor, 'limit' => min(self::PAGE, $limit - count($items))];
            $response = $this->shopifyResponse($run, $config, 'GET', $path, query: $query);
            array_push($items, ...array_values(array_filter((array) $response->json($key), 'is_array')));
            $cursor = self::nextCursor($response->header('Link'));
        } while ($cursor !== null && count($items) < $limit);

        return ['items' => array_slice($items, 0, $limit), 'has_more' => $cursor !== null || count($items) > $limit];
    }

    /**
     * The `page_info` of the `rel="next"` link, if there is one.
     */
    public static function nextCursor(?string $link): ?string
    {
        if ($link === null || ! preg_match('/<([^>]+)>;\s*rel="next"/', $link, $match)) {
            return null;
        }

        parse_str((string) parse_url($match[1], PHP_URL_QUERY), $query);

        return is_string($query['page_info'] ?? null) && $query['page_info'] !== '' ? $query['page_info'] : null;
    }

    /**
     * A list node's output: the records under their name, how many, and whether
     * Shopify has more.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function listOutput(Run $run, array $config, string $path, string $key, array $params, int $default = 25): array
    {
        $result = $this->listAll($run, $config, $path, $key, $this->encode($params), $this->limit($config, $default));

        return [$key => $result['items'], 'count' => count($result['items']), 'has_more' => $result['has_more']];
    }

    /**
     * An id Shopify gave out: digits, or the `gid://shopify/Order/123` form.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function shopifyId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("The {$label} is required.");
        }

        if (preg_match('~^gid://shopify/[A-Za-z]+/(\d+)~', $value, $match) === 1) {
            $value = $match[1];
        }

        return preg_match('/^\d{1,20}$/', $value) === 1 ? $value : throw new InvalidArgumentException("\"{$value}\" is not a Shopify {$label}. It is a number.");
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function optionalShopifyId(array $config, string $key, string $label): ?string
    {
        return filled($config[$key] ?? null) ? $this->shopifyId($config, $key, $label) : null;
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
     * A price as Shopify writes it: a decimal string such as "19.99".
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function price(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return preg_match('/^\d{1,12}(\.\d{1,2})?$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} is an amount such as 19.99.");
    }

    /**
     * How many records a list node reads: its `limit`, between 1 and `$max`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default = 25, int $max = 1000): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * A JSON setting that may arrive as text from a template.
     *
     * @return array<int|string, mixed>|null
     *
     * @throws InvalidArgumentException
     */
    protected function decoded(mixed $value, string $label): ?array
    {
        if (is_string($value)) {
            if (trim($value) === '') {
                return null;
            }

            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : throw new InvalidArgumentException("{$label} must be valid JSON.");
        }

        return is_array($value) ? $value : null;
    }

    /**
     * The values among `$keys` that the step set.
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    protected function filled(array $config, array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            if (filled($config[$key] ?? null)) {
                $values[$key] = (string) $config[$key];
            }
        }

        return $values;
    }

    /**
     * Tags as Shopify wants them: one comma-separated line.
     *
     * @param  array<string, mixed>  $config
     */
    protected function tags(array $config, string $key = 'tags'): ?string
    {
        $tags = $config[$key] ?? null;
        $tags = is_array($tags) ? $tags : (is_string($tags) ? explode(',', $tags) : []);
        $tags = array_values(array_filter(array_map(fn (mixed $tag): string => trim((string) $tag), $tags)));

        return $tags === [] ? null : implode(', ', $tags);
    }

    /**
     * Line items as a list of `{variant_id, quantity}` (or a custom `{title, price, quantity}`).
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    protected function lineItems(array $config): array
    {
        $items = $this->decoded($config['line_items'] ?? null, 'The line items') ?? [];
        $lines = [];

        foreach (array_values(array_filter($items, 'is_array')) as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if (isset($item['variant_id'])) {
                $lines[] = ['variant_id' => (int) $this->shopifyId($item, 'variant_id', 'variant id'), 'quantity' => $quantity];
            } elseif (filled($item['title'] ?? null) && isset($item['price'])) {
                $lines[] = ['title' => (string) $item['title'], 'price' => $this->price($item, 'price', 'The price'), 'quantity' => $quantity];
            } else {
                throw new InvalidArgumentException('Each line item needs a variant_id, or a title and a price.');
            }
        }

        return $lines !== [] ? $lines : throw new InvalidArgumentException('Add at least one line item.');
    }
}
