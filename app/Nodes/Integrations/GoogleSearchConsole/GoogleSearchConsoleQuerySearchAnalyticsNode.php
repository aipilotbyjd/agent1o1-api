<?php

namespace App\Nodes\Integrations\GoogleSearchConsole;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleSearchConsoleQuerySearchAnalyticsNode extends AbstractGoogleSearchConsoleNode
{
    private const array DIMENSIONS = ['query', 'page', 'country', 'device', 'date'];

    public function type(): string
    {
        return 'google_search_console_query_search_analytics';
    }

    public function name(): string
    {
        return 'Google Search Console: Search Analytics';
    }

    public function description(): string
    {
        return 'Clicks, impressions, click-through rate and average position, by query, page, country, device or date.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['site_url', 'start_date', 'end_date'],
            'properties' => [
                ...$this->credentialFields(),
                'site_url' => $this->siteField(),
                'start_date' => Field::text('From', 'A date as YYYY-MM-DD.', '2026-01-01'),
                'end_date' => Field::text('To', 'A date as YYYY-MM-DD. Search Console data is a few days behind.', '2026-01-31'),
                'dimensions' => Field::text('Break down by', 'Comma separated: query, page, country, device, date. Leave empty for one row of totals.', 'query, page'),
                'search_type' => Field::select('Search type', [
                    'web' => 'Web',
                    'image' => 'Image',
                    'video' => 'Video',
                    'news' => 'News',
                    'discover' => 'Discover',
                    'googleNews' => 'Google News',
                ], null, 'web'),
                'row_limit' => Field::integer('Max rows', null, 100, 1, 25000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $asked = array_map('mb_strtolower', preg_split('/[\s,]+/', (string) ($config['dimensions'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $dimensions = array_values(array_intersect($asked, self::DIMENSIONS));

        $response = $this->post($run, $this->sitePath($config).'/searchAnalytics/query', $config, array_filter([
            'startDate' => $config['start_date'],
            'endDate' => $config['end_date'],
            'dimensions' => $dimensions,
            'type' => $config['search_type'] ?? 'web',
            'rowLimit' => (int) ($config['row_limit'] ?? 100),
        ], fn (mixed $value): bool => $value !== []));

        $rows = array_map(function (array $row) use ($dimensions): array {
            $result = [];

            foreach ($dimensions as $index => $dimension) {
                $result[$dimension] = $row['keys'][$index] ?? null;
            }

            return [
                ...$result,
                'clicks' => $row['clicks'] ?? 0,
                'impressions' => $row['impressions'] ?? 0,
                'ctr' => round((float) ($row['ctr'] ?? 0), 4),
                'position' => round((float) ($row['position'] ?? 0), 2),
            ];
        }, (array) ($response['rows'] ?? []));

        return ['rows' => $rows, 'row_count' => count($rows)];
    }
}
