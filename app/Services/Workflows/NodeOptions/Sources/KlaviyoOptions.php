<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The account's lists, segments, metrics and flows. Klaviyo takes the private key
 * in an `Authorization` header with a revision, and pages by a cursor it returns
 * inside the link to the next page. It can't search, so a search filters what was
 * fetched.
 */
class KlaviyoOptions extends HttpOptionsSource
{
    private const string API = 'https://a.klaviyo.com/api';

    /**
     * Each source's path and what an item is called.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const array COLLECTIONS = [
        'klaviyo.lists' => ['/lists', 'attributes.name'],
        'klaviyo.segments' => ['/segments', 'attributes.name'],
        'klaviyo.metrics' => ['/metrics', 'attributes.name'],
        'klaviyo.flows' => ['/flows', 'attributes.name'],
    ];

    public function sources(): array
    {
        return array_keys(self::COLLECTIONS);
    }

    protected function appName(): string
    {
        return 'Klaviyo';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Klaviyo-API-Key '.$query->token, 'revision' => '2024-10-15', 'Accept' => 'application/vnd.api+json'])->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        [$path, $label] = self::COLLECTIONS[$source];
        $body = $this->getJson($query, self::API.$path, ['page[cursor]' => $query->cursor]);
        $next = data_get($body, 'links.next');
        $cursor = null;

        if (is_string($next) && $next !== '') {
            parse_str((string) parse_url($next, PHP_URL_QUERY), $params);
            $cursor = $params['page']['cursor'] ?? null;
        }

        return new NodeOptionsPage(
            collect($body['data'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && $query->matches((string) data_get($item, $label, '')))
                ->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], (string) data_get($item, $label, $item['id']), $source === 'klaviyo.flows' ? (string) data_get($item, 'attributes.status') : null)),
            $cursor,
        );
    }
}
