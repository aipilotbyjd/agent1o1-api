<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;

/**
 * The sites the member has in Search Console. The value is the site URL
 * (`https://example.com/` or `sc-domain:example.com`) the reports take.
 */
class GoogleSearchConsoleOptions extends HttpOptionsSource
{
    private const string SITES_URL = 'https://www.googleapis.com/webmasters/v3/sites';

    public function sources(): array
    {
        return ['google_search_console.sites'];
    }

    protected function appName(): string
    {
        return 'Google Search Console';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $body = $this->getJson($query, self::SITES_URL);

        $options = collect($body['siteEntry'] ?? [])
            ->filter(fn (mixed $site): bool => is_array($site) && isset($site['siteUrl']) && $query->matches((string) $site['siteUrl']))
            ->map(fn (array $site): NodeOption => new NodeOption(
                (string) $site['siteUrl'],
                null,
                isset($site['permissionLevel']) ? str_replace('site', '', (string) $site['permissionLevel']) : null,
            ));

        return new NodeOptionsPage($options);
    }
}
