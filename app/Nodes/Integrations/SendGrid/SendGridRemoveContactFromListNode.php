<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SendGridRemoveContactFromListNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_remove_contact_from_list';
    }

    public function name(): string
    {
        return 'SendGrid: Remove Contact from List';
    }

    public function description(): string
    {
        return 'Takes a contact off a marketing list. They stay in SendGrid.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->listField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->identifier($config, 'list_id', 'The list');
        $email = $this->email($config);
        $contact = $this->findContact($run, $config, $email);

        if ($contact === null || ! isset($contact['id'])) {
            return ['removed' => false, 'found' => false, 'email' => $email];
        }

        $this->sendgrid($run, $config, 'DELETE', '/marketing/lists/'.rawurlencode($id).'/contacts', query: ['contact_ids' => $contact['id']]);

        return ['removed' => true, 'found' => true, 'email' => $email, 'list_id' => $id];
    }
}
