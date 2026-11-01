<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The account's tags, forms, sequences and email templates. Kit takes the key in
 * a header and pages by an `after` cursor; it can't search, so a search filters
 * the fetched page.
 */
class KitOptions extends HttpOptionsSource
{
    private const string API = 'https://api.kit.com/v4';

    /**
     * Which collection each source reads, and the label of one.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const array COLLECTIONS = [
        'kit.tags' => ['/tags', 'tags'],
        'kit.forms' => ['/forms', 'forms'],
        'kit.sequences' => ['/sequences', 'sequences'],
        'kit.templates' => ['/email_templates', 'email_templates'],
    ];

    public function sources(): array
    {
        return array_keys(self::COLLECTIONS);
    }

    protected function appName(): string
    {
        return 'Kit';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['X-Kit-Api-Key' => (string) $query->token])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        [$path, $key] = self::COLLECTIONS[$source];
        $body = $this->getJson($query, self::API.$path, ['per_page' => 200, 'after' => $query->cursor]);

        return new NodeOptionsPage(
            collect($body[$key] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && $query->matches((string) ($item['name'] ?? '')))
                ->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], $item['name'] ?? null, $source === 'kit.forms' ? ($item['type'] ?? null) : null)),
            data_get($body, 'pagination.has_next_page') === true ? data_get($body, 'pagination.end_cursor') : null,
        );
    }
}
