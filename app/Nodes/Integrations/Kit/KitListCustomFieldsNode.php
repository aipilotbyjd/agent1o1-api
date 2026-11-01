<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KitListCustomFieldsNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_list_custom_fields';
    }

    public function name(): string
    {
        return 'Kit: List Custom Fields';
    }

    public function description(): string
    {
        return 'Lists the custom subscriber fields of the account.';
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
        $fields = $this->listAll($run, $config, '/custom_fields', 'custom_fields', [], 1000);

        return ['custom_fields' => array_map(fn (array $field): array => ['id' => $field['id'] ?? null, 'key' => $field['key'] ?? null, 'label' => $field['label'] ?? null], $fields), 'count' => count($fields)];
    }
}
