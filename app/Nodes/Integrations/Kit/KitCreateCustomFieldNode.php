<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitCreateCustomFieldNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_create_custom_field';
    }

    public function name(): string
    {
        return 'Kit: Create Custom Field';
    }

    public function description(): string
    {
        return 'Adds a custom subscriber field.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['label'],
            'properties' => [
                ...$this->credentialFields(),
                'label' => Field::text('Label', null, 'Company'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $field = (array) data_get((array) $this->kit($run, $config, 'POST', '/custom_fields', ['label' => $this->requiredString($config, 'label', 'The label')]), 'custom_field', []);

        return ['id' => $field['id'] ?? null, 'key' => $field['key'] ?? null, 'label' => $field['label'] ?? null];
    }
}
