<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class HubSpotListPipelinesNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_list_pipelines';
    }

    public function name(): string
    {
        return 'HubSpot: List Pipelines';
    }

    public function description(): string
    {
        return 'Lists the deal or ticket pipelines with their stages and ids.';
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
                'object' => Field::select('Of', ['deals' => 'Deals', 'tickets' => 'Tickets'], null, 'deals'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $object = ($config['object'] ?? 'deals') === 'tickets' ? 'tickets' : 'deals';
        $pipelines = array_values(array_filter((array) ($this->hubspot($run, $config, 'GET', "/crm/v3/pipelines/{$object}")['results'] ?? []), 'is_array'));

        return ['pipelines' => array_map(fn (array $pipeline): array => ['id' => $pipeline['id'] ?? null, 'label' => $pipeline['label'] ?? null, 'stages' => array_values(array_map(fn (array $stage): array => ['id' => $stage['id'] ?? null, 'label' => $stage['label'] ?? null, 'order' => $stage['displayOrder'] ?? null], array_filter((array) ($pipeline['stages'] ?? []), 'is_array')))], $pipelines), 'count' => count($pipelines)];
    }
}
