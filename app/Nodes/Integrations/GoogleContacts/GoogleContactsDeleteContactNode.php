<?php

namespace App\Nodes\Integrations\GoogleContacts;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class GoogleContactsDeleteContactNode extends AbstractGoogleContactsNode
{
    public function type(): string
    {
        return 'google_contacts_delete_contact';
    }

    public function name(): string
    {
        return 'Google Contacts: Delete Contact';
    }

    public function description(): string
    {
        return 'Deletes a contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['resource_name'],
            'properties' => [
                ...$this->credentialFields(),
                'resource_name' => $this->resourceNameField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->delete($run, $this->personPath($config['resource_name']).':deleteContact', $config);

        return ['deleted' => true, 'resource_name' => $config['resource_name']];
    }
}
