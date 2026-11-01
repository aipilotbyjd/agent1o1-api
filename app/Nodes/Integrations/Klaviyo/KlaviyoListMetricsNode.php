<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KlaviyoListMetricsNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_list_metrics';
    }

    public function name(): string
    {
        return 'Klaviyo: List Metrics';
    }

    public function description(): string
    {
        return 'Lists the account\'s metrics (the kinds of event it records, like Placed Order).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        [$metrics] = $this->listAll($run, $config, '/metrics', [], 1000, 200);

        return ['metrics' => array_map(fn (array $metric): array => ['id' => $metric['id'] ?? null, 'name' => data_get($metric, 'attributes.name'), 'integration' => data_get($metric, 'attributes.integration.name'), 'created_at' => data_get($metric, 'attributes.created')], $metrics), 'count' => count($metrics)];
    }
}
