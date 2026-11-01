<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;

/**
 * GA4 properties across the accounts the member can see. The value is the
 * numeric property ID the reports take.
 */
class GoogleAnalyticsOptions extends HttpOptionsSource
{
    private const string ACCOUNT_SUMMARIES_URL = 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries';

    public function sources(): array
    {
        return ['google_analytics.properties'];
    }

    protected function appName(): string
    {
        return 'Google Analytics';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $body = $this->getJson($query, self::ACCOUNT_SUMMARIES_URL, ['pageSize' => 200, 'pageToken' => $query->cursor]);

        $options = collect($body['accountSummaries'] ?? [])
            ->filter(fn (mixed $account): bool => is_array($account))
            ->flatMap(fn (array $account) => collect($account['propertySummaries'] ?? [])
                ->filter(fn (mixed $property): bool => is_array($property) && isset($property['property']))
                ->filter(fn (array $property): bool => $query->matches((string) ($property['displayName'] ?? ''), (string) ($account['displayName'] ?? '')))
                ->map(fn (array $property): NodeOption => new NodeOption(
                    (string) preg_replace('#^properties/#', '', (string) $property['property']),
                    $property['displayName'] ?? null,
                    $account['displayName'] ?? null,
                )));

        return new NodeOptionsPage($options, $body['nextPageToken'] ?? null);
    }
}
