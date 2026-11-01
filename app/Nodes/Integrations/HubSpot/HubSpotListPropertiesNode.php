<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class HubSpotListPropertiesNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_list_properties';
    }

    public function name(): string
    {
        return 'HubSpot: List Properties';
    }

    public function description(): string
    {
        return 'Lists the properties of contacts, companies, deals or tickets, with their internal names and types.';
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
                'object' => Field::select('Of', ['contacts' => 'Contacts', 'companies' => 'Companies', 'deals' => 'Deals', 'tickets' => 'Tickets'], null, 'contacts'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $object = $this->objectOf($config);
        $properties = array_values(array_filter((array) ($this->hubspot($run, $config, 'GET', "/crm/v3/properties/{$object}")['results'] ?? []), 'is_array'));

        return ['properties' => array_map(fn (array $property): array => ['name' => $property['name'] ?? null, 'label' => $property['label'] ?? null, 'type' => $property['type'] ?? null, 'group' => $property['groupName'] ?? null, 'options' => array_values(array_map(fn (array $option): array => ['value' => $option['value'] ?? null, 'label' => $option['label'] ?? null], array_filter((array) ($property['options'] ?? []), 'is_array')))], $properties), 'count' => count($properties)];
    }
}
