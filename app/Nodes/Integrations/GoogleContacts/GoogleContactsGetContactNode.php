<?php

namespace App\Nodes\Integrations\GoogleContacts;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class GoogleContactsGetContactNode extends AbstractGoogleContactsNode
{
    public function type(): string
    {
        return 'google_contacts_get_contact';
    }

    public function name(): string
    {
        return 'Google Contacts: Get Contact';
    }

    public function description(): string
    {
        return 'Reads one contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        return $this->contact($this->get($run, $this->personPath($config['resource_name']), $config, ['personFields' => self::PERSON_FIELDS]));
    }
}
