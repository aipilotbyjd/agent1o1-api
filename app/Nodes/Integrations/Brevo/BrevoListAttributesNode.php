<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class BrevoListAttributesNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_list_attributes';
    }

    public function name(): string
    {
        return 'Brevo: List Contact Fields';
    }

    public function description(): string
    {
        return 'Lists the contact fields (attributes) the account has, with their types.';
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
        $attributes = array_values(array_filter((array) data_get((array) $this->brevo($run, $config, 'GET', '/contacts/attributes'), 'attributes', []), 'is_array'));

        return ['attributes' => array_map(fn (array $attribute): array => ['name' => $attribute['name'] ?? null, 'category' => $attribute['category'] ?? null, 'type' => $attribute['type'] ?? null], $attributes), 'count' => count($attributes)];
    }
}
