<?php

namespace App\Nodes\Integrations\Brevo;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * "Contact updated". Reads the contacts Brevo says changed since a look-back
 * (`modifiedSince`); the time of the change is part of the item's id, so every
 * edit of a person is its own event. A new contact appears here too.
 */
class BrevoListUpdatedContactsNode extends AbstractBrevoNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    public function type(): string
    {
        return 'brevo_list_updated_contacts';
    }

    public function name(): string
    {
        return 'Brevo: List Updated Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts changed lately, most recent first. As a trigger it starts the flow whenever a contact is edited (or added).';
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
                'list_id' => Field::advanced($this->listField('Only on list')),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only changes this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $contacts = $this->listAll($run, $config, is_numeric($config['list_id'] ?? null) ? '/contacts/lists/'.(int) $config['list_id'].'/contacts' : '/contacts', 'contacts', [
            'modifiedSince' => Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc()->toIso8601String(),
            'sort' => 'desc',
        ], $this->limit($config, 25));

        return ['contacts' => array_map($this->shapeContact(...), $contacts), 'count' => count($contacts)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'contacts';
    }

    public function triggerItemId(array $item): string
    {
        return ($item['id'] ?? '').':'.($item['modified_at'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Brevo: Contact updated';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is edited in Brevo. Checks every few minutes.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
