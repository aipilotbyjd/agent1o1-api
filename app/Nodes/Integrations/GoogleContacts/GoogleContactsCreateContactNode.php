<?php

namespace App\Nodes\Integrations\GoogleContacts;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use InvalidArgumentException;

class GoogleContactsCreateContactNode extends AbstractGoogleContactsNode
{
    public function type(): string
    {
        return 'google_contacts_create_contact';
    }

    public function name(): string
    {
        return 'Google Contacts: Create Contact';
    }

    public function description(): string
    {
        return 'Adds a contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->personFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->personBody($config);

        if ($body === []) {
            throw new InvalidArgumentException('Google Contacts: fill in at least a name, an email address or a phone number.');
        }

        return $this->contact($this->post($run, '/people:createContact?'.http_build_query(['personFields' => self::PERSON_FIELDS]), $config, $body));
    }
}
