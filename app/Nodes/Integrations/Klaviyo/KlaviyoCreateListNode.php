<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KlaviyoCreateListNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_create_list';
    }

    public function name(): string
    {
        return 'Klaviyo: Create List';
    }

    public function description(): string
    {
        return 'Creates a list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'Newsletter'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeList((array) data_get((array) $this->klaviyo($run, $config, 'POST', '/lists/', ['data' => ['type' => 'list', 'attributes' => ['name' => $this->requiredString($config, 'name', 'The name')]]]), 'data', []));
    }
}
