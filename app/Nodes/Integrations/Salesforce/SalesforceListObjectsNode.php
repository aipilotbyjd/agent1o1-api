<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SalesforceListObjectsNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_list_objects';
    }

    public function name(): string
    {
        return 'Salesforce: List Objects';
    }

    public function description(): string
    {
        return 'Lists the objects in the org you can create records of, standard and custom.';
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
        $objects = array_values(array_filter((array) ($this->salesforce($run, $config, 'GET', '/sobjects')['sobjects'] ?? []), fn (mixed $object): bool => is_array($object) && ($object['createable'] ?? false)));

        return ['objects' => array_map(fn (array $object): array => ['name' => $object['name'] ?? null, 'label' => $object['label'] ?? null, 'custom' => (bool) ($object['custom'] ?? false)], $objects), 'count' => count($objects)];
    }
}
