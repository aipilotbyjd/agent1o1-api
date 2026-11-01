<?php

namespace App\Nodes\Integrations\GoogleAnalytics;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GoogleAnalyticsRunReportNode extends AbstractGoogleAnalyticsNode
{
    public function type(): string
    {
        return 'google_analytics_run_report';
    }

    public function name(): string
    {
        return 'Google Analytics: Run Report';
    }

    public function description(): string
    {
        return 'Runs a GA4 report: pick metrics (sessions, totalUsers...) and dimensions (date, country...) for a date range.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['property_id', 'metrics'],
            'properties' => [
                ...$this->credentialFields(),
                'property_id' => $this->propertyField(),
                'start_date' => Field::text('From', 'A date such as 2026-01-31, or 7daysAgo, 30daysAgo, yesterday, today.', '7daysAgo'),
                'end_date' => Field::text('To', 'A date such as 2026-01-31, or yesterday, today.', 'yesterday'),
                'metrics' => Field::text('Metrics', 'Comma separated API names, e.g. sessions, totalUsers, screenPageViews.', 'sessions, totalUsers'),
                'dimensions' => Field::text('Break down by', 'Comma separated API names, e.g. date, country, sessionSource. Leave empty for totals.', 'date'),
                'order_by' => Field::text('Sort by', 'A metric or dimension name from above.', 'sessions'),
                'descending' => Field::boolean('Sort high to low', null, true),
                'limit' => Field::integer('Max rows', null, 100, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $metrics = $this->names($config['metrics'] ?? '');

        if ($metrics === []) {
            throw new InvalidArgumentException('Google Analytics: choose at least one metric.');
        }

        $orderBy = trim((string) ($config['order_by'] ?? ''));
        $dimensions = $this->names($config['dimensions'] ?? '');

        $report = $this->post($run, $this->propertyPath($config).':runReport', $config, array_filter([
            'dateRanges' => [[
                'startDate' => trim((string) ($config['start_date'] ?? '')) ?: '7daysAgo',
                'endDate' => trim((string) ($config['end_date'] ?? '')) ?: 'yesterday',
            ]],
            'metrics' => array_map(fn (string $name): array => ['name' => $name], $metrics),
            'dimensions' => array_map(fn (string $name): array => ['name' => $name], $dimensions),
            'orderBys' => $orderBy === '' ? null : [in_array($orderBy, $metrics, true)
                ? ['metric' => ['metricName' => $orderBy], 'desc' => (bool) ($config['descending'] ?? true)]
                : ['dimension' => ['dimensionName' => $orderBy], 'desc' => (bool) ($config['descending'] ?? true)]],
            'limit' => (string) ($config['limit'] ?? 100),
        ]));

        return $this->rows($report);
    }
}
