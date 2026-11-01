<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SendGridDeleteContactNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_delete_contact';
    }

    public function name(): string
    {
        return 'SendGrid: Delete Contact';
    }

    public function description(): string
    {
        return 'Deletes a marketing contact by email. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $email = $this->email($config);
        $contact = $this->findContact($run, $config, $email);

        if ($contact === null || ! isset($contact['id'])) {
            return ['deleted' => false, 'found' => false, 'email' => $email];
        }

        $result = (array) $this->sendgrid($run, $config, 'DELETE', '/marketing/contacts', query: ['ids' => $contact['id']]);

        return ['deleted' => true, 'found' => true, 'email' => $email, 'job_id' => $result['job_id'] ?? null];
    }
}
