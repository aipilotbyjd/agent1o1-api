<?php

namespace App\Nodes\Integrations\GoogleContacts;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GoogleContactsListContactsNode extends AbstractGoogleContactsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'google_contacts_list_contacts';
    }

    public function name(): string
    {
        return 'Google Contacts: List Contacts';
    }

    public function description(): string
    {
        return 'Lists your contacts, most recently changed first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'max_results' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, '/people/me/connections', $config, [
            'personFields' => self::PERSON_FIELDS,
            'pageSize' => $config['max_results'] ?? 50,
            'sortOrder' => 'LAST_MODIFIED_DESCENDING',
        ]);

        return [
            'contacts' => array_map(fn (array $person): array => $this->contact($person), (array) ($response['connections'] ?? [])),
            'total' => (int) ($response['totalPeople'] ?? 0),
        ];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'contacts';
    }

    /**
     * A contact's resource name is its stable id.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['resource_name'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Contacts: New contact';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is added.';
    }

    /**
     * Read a wider page than the node's default, so a burst between two polls
     * isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => 200];
    }

    /**
     * Newest first, as the list is requested.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }
}
