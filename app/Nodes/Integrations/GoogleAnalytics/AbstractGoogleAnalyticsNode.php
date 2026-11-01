<?php

namespace App\Nodes\Integrations\GoogleAnalytics;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Nodes\Integrations\Concerns\CallsGoogleJsonApi;
use App\Nodes\Support\Field;

/**
 * Shared request handling and report shaping for the Google Analytics (GA4)
 * node family.
 */
abstract class AbstractGoogleAnalyticsNode implements DeclaresEffect, NodeContract
{
    use CallsGoogleJsonApi;

    public function category(): string
    {
        return 'google_analytics';
    }

    protected function baseUrl(): string
    {
        return 'https://analyticsdata.googleapis.com/v1beta';
    }

    protected function appName(): string
    {
        return 'Google Analytics';
    }

    /**
     * @return array<string, mixed>
     */
    protected function propertyField(): array
    {
        return Field::dynamic('Property', 'google_analytics.properties', 'Pick a GA4 property, or enter its numeric ID.', '123456789');
    }

    /**
     * `/properties/{id}` with the numeric ID checked, so the value can only
     * ever name a property.
     *
     * @param  array<string, mixed>  $config
     */
    protected function propertyPath(array $config): string
    {
        $id = trim((string) preg_replace('#^properties/#', '', trim((string) $config['property_id'])));

        if (preg_match('/^\d+$/', $id) !== 1) {
            throw new \InvalidArgumentException('Google Analytics: the property ID is a number, such as 123456789.');
        }

        return "/properties/{$id}";
    }

    /**
     * Names from a comma separated string or a list.
     *
     * @return list<string>
     */
    protected function names(mixed $value): array
    {
        $parts = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(array_map(fn (mixed $name): string => trim((string) $name), (array) $parts))));
    }

    /**
     * A report as rows keyed by dimension and metric name. Metric values are
     * numbers; dimension values stay text.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    protected function rows(array $report): array
    {
        $dimensions = array_map(fn (array $header): string => (string) ($header['name'] ?? ''), (array) ($report['dimensionHeaders'] ?? []));
        $metrics = array_map(fn (array $header): string => (string) ($header['name'] ?? ''), (array) ($report['metricHeaders'] ?? []));

        $rows = array_map(function (array $row) use ($dimensions, $metrics): array {
            $result = [];

            foreach ($dimensions as $index => $name) {
                $result[$name] = $row['dimensionValues'][$index]['value'] ?? null;
            }

            foreach ($metrics as $index => $name) {
                $value = $row['metricValues'][$index]['value'] ?? null;
                $result[$name] = is_numeric($value) ? $value + 0 : $value;
            }

            return $result;
        }, (array) ($report['rows'] ?? []));

        return ['rows' => $rows, 'row_count' => (int) ($report['rowCount'] ?? count($rows))];
    }
}
