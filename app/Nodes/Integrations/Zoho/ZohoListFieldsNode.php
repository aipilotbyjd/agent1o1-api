<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoListFieldsNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_list_fields';
    }

    public function name(): string
    {
        return 'Zoho CRM: List Fields';
    }

    public function description(): string
    {
        return 'Lists a module\'s fields with their API names, types and picklist values.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['module'],
            'properties' => [
                ...$this->credentialFields(),
                'module' => Field::dynamic('Module', 'zoho.modules', null, 'Leads', allowCustom: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $module = $this->apiName((string) ($config['module'] ?? ''));
        $fields = array_values(array_filter((array) ($this->zoho($run, $config, 'GET', '/settings/fields', ['module' => $module])['fields'] ?? []), 'is_array'));

        return ['fields' => array_map(fn (array $field): array => ['api_name' => $field['api_name'] ?? null, 'label' => $field['field_label'] ?? null, 'type' => $field['data_type'] ?? null, 'required' => (bool) ($field['system_mandatory'] ?? false), 'values' => array_values(array_map(fn (array $value): string => (string) ($value['actual_value'] ?? $value['display_value'] ?? ''), array_filter((array) ($field['pick_list_values'] ?? []), 'is_array')))], $fields), 'count' => count($fields)];
    }
}
