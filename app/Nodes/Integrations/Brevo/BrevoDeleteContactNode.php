<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class BrevoDeleteContactNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_delete_contact';
    }

    public function name(): string
    {
        return 'Brevo: Delete Contact';
    }

    public function description(): string
    {
        return 'Deletes a contact and their history. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $path = $this->contactPath($config);

        $this->brevo($run, $config, 'DELETE', $path);

        return ['deleted' => true, 'contact' => trim((string) $config['contact'])];
    }
}
