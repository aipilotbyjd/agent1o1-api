<?php

namespace App\Nodes\Integrations\GoogleAnalytics;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GoogleAnalyticsRunRealtimeReportNode extends AbstractGoogleAnalyticsNode
{
    public function type(): string
    {
        return 'google_analytics_run_realtime_report';
    }

    public function name(): string
    {
        return 'Google Analytics: Run Realtime Report';
    }

    public function description(): string
    {
        return 'Who is on your site right now: activeUsers by country, device or page, for the last 30 minutes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['property_id'],
            'properties' => [
                ...$this->credentialFields(),
                'property_id' => $this->propertyField(),
                'metrics' => Field::text('Metrics', 'Comma separated API names.', 'activeUsers'),
                'dimensions' => Field::text('Break down by', 'Comma separated API names, e.g. country, deviceCategory, unifiedScreenName.', 'country'),
                'limit' => Field::integer('Max rows', null, 50, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $metrics = $this->names($config['metrics'] ?? '') ?: ['activeUsers'];
        $dimensions = $this->names($config['dimensions'] ?? '');

        if ($metrics === []) {
            throw new InvalidArgumentException('Google Analytics: choose at least one metric.');
        }

        $report = $this->post($run, $this->propertyPath($config).':runRealtimeReport', $config, array_filter([
            'metrics' => array_map(fn (string $name): array => ['name' => $name], $metrics),
            'dimensions' => array_map(fn (string $name): array => ['name' => $name], $dimensions),
            'limit' => (string) ($config['limit'] ?? 50),
        ]));

        return $this->rows($report);
    }
}
