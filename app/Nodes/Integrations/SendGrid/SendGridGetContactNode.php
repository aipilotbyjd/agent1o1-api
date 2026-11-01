<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SendGridGetContactNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_get_contact';
    }

    public function name(): string
    {
        return 'SendGrid: Get Contact';
    }

    public function description(): string
    {
        return 'Finds a marketing contact by email. Says whether they exist.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $contact = $this->findContact($run, $config, $this->email($config));

        return $contact === null ? ['found' => false, 'email' => $this->email($config)] : [...$this->shapeContact($contact), 'found' => true];
    }
}
