<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ClickUpListCustomFieldsNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_list_custom_fields';
    }

    public function name(): string
    {
        return 'ClickUp: List Custom Fields';
    }

    public function description(): string
    {
        return 'Lists the custom fields available on a list, with their ids and types.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                ...$this->locationFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = array_values(array_filter((array) ($this->clickup($run, $config, 'GET', '/list/'.$this->numericId($config, 'list_id', 'The list').'/field')['fields'] ?? []), 'is_array'));

        return ['fields' => array_map(fn (array $field): array => ['id' => $field['id'] ?? null, 'name' => $field['name'] ?? null, 'type' => $field['type'] ?? null, 'options' => array_values(array_map(fn (array $option): array => ['id' => $option['id'] ?? null, 'name' => $option['name'] ?? $option['label'] ?? null], array_filter((array) data_get($field, 'type_config.options', []), 'is_array')))], $fields), 'count' => count($fields)];
    }
}
