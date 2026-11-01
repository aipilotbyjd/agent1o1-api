<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class BrevoGetContactNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_get_contact';
    }

    public function name(): string
    {
        return 'Brevo: Get Contact';
    }

    public function description(): string
    {
        return 'Gets a contact by email or id, with their fields and lists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact'],
            'properties' => [
                ...$this->credentialFields(),
                'contact' => $this->contactField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeContact((array) $this->brevo($run, $config, 'GET', $this->contactPath($config)));
    }
}
