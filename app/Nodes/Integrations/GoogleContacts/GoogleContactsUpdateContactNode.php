<?php

namespace App\Nodes\Integrations\GoogleContacts;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use InvalidArgumentException;

class GoogleContactsUpdateContactNode extends AbstractGoogleContactsNode
{
    public function type(): string
    {
        return 'google_contacts_update_contact';
    }

    public function name(): string
    {
        return 'Google Contacts: Update Contact';
    }

    public function description(): string
    {
        return 'Changes a contact. Fields you leave empty stay as they are; an email or phone you set replaces the old ones.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['resource_name'],
            'properties' => [
                ...$this->credentialFields(),
                'resource_name' => $this->resourceNameField(),
                ...$this->personFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = $this->personPath($config['resource_name']);
        $body = $this->personBody($config);

        if ($body === []) {
            throw new InvalidArgumentException('Google Contacts: choose at least one field to change.');
        }

        // An update must carry the contact's current etag, or Google refuses it as stale.
        $current = $this->get($run, $path, $config, ['personFields' => 'metadata']);

        $query = http_build_query(['updatePersonFields' => implode(',', array_keys($body)), 'personFields' => self::PERSON_FIELDS]);

        return $this->contact($this->patch($run, "{$path}:updateContact?{$query}", $config, [...$body, 'etag' => $current['etag'] ?? null]));
    }
}
