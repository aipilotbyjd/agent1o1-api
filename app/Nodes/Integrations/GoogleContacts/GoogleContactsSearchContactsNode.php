<?php

namespace App\Nodes\Integrations\GoogleContacts;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleContactsSearchContactsNode extends AbstractGoogleContactsNode
{
    public function type(): string
    {
        return 'google_contacts_search_contacts';
    }

    public function name(): string
    {
        return 'Google Contacts: Search Contacts';
    }

    public function description(): string
    {
        return 'Finds contacts by name, email address or phone number.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search for', 'Part of a name, an email address or a phone number.', 'ada@example.com'),
                'max_results' => Field::integer('Max results', null, 10, 1, 30),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, '/people:searchContacts', $config, [
            'query' => $config['query'],
            'readMask' => self::PERSON_FIELDS,
            'pageSize' => $config['max_results'] ?? 10,
        ]);

        return ['contacts' => array_map(
            fn (array $result): array => $this->contact((array) ($result['person'] ?? [])),
            (array) ($response['results'] ?? []),
        )];
    }
}
