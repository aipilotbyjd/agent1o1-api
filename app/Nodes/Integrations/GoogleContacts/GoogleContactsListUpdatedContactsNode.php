<?php

namespace App\Nodes\Integrations\GoogleContacts;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GoogleContactsListUpdatedContactsNode extends AbstractGoogleContactsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'google_contacts_list_updated_contacts';
    }

    public function name(): string
    {
        return 'Google Contacts: List Recently Changed Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts that were added or edited most recently.';
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
                'max_results' => Field::integer('Max results', null, 25, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, '/people/me/connections', $config, [
            'personFields' => self::PERSON_FIELDS,
            'pageSize' => $config['max_results'] ?? 25,
            'sortOrder' => 'LAST_MODIFIED_DESCENDING',
        ]);

        return ['contacts' => array_map(fn (array $person): array => $this->contact($person), (array) ($response['connections'] ?? []))];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'contacts';
    }

    /**
     * A contact is a new item each time it changes: Google gives it a new
     * etag with every edit.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['resource_name'], $item['etag']) ? $item['resource_name'].':'.$item['etag'] : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Contacts: Contact added or updated';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is added or edited.';
    }

    /**
     * Read a wider page than the node's default, so a burst between two polls
     * isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => 100];
    }

    /**
     * Newest first, as the list is requested.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * Contacts change rarely and Google has no push for them, so a slow check.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }
}
