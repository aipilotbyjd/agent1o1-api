<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SalesforceDescribeObjectNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_describe_object';
    }

    public function name(): string
    {
        return 'Salesforce: Describe Object';
    }

    public function description(): string
    {
        return 'Lists an object\'s fields with their API names, types and picklist values.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['object'],
            'properties' => [
                ...$this->credentialFields(),
                'object' => Field::dynamic('Object', 'salesforce.objects', null, 'Opportunity', allowCustom: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $object = $this->apiName((string) ($config['object'] ?? ''));
        $found = $this->salesforce($run, $config, 'GET', "/sobjects/{$object}/describe");
        $fields = array_values(array_filter((array) ($found['fields'] ?? []), 'is_array'));

        return ['name' => $found['name'] ?? $object, 'label' => $found['label'] ?? null, 'fields' => array_map(fn (array $field): array => ['name' => $field['name'] ?? null, 'label' => $field['label'] ?? null, 'type' => $field['type'] ?? null, 'required' => ! ($field['nillable'] ?? true) && ($field['createable'] ?? false) && ! ($field['defaultedOnCreate'] ?? false), 'values' => array_values(array_map(fn (array $option): string => (string) ($option['value'] ?? ''), array_filter((array) ($field['picklistValues'] ?? []), fn (mixed $option): bool => is_array($option) && ($option['active'] ?? false))))], $fields), 'count' => count($fields)];
    }
}
