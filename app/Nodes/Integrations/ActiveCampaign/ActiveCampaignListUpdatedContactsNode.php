<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * "Contact updated". Reads the contacts changed since a look-back; the time of
 * the change is part of the item's id, so every edit of a person is its own event.
 * A new contact appears here too (it was just changed), so a flow that only wants
 * existing people being edited should use the new-contact trigger's complement
 * and check `created_at`.
 */
class ActiveCampaignListUpdatedContactsNode extends AbstractActiveCampaignNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    public function type(): string
    {
        return 'activecampaign_list_updated_contacts';
    }

    public function name(): string
    {
        return 'ActiveCampaign: List Updated Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts changed lately, most recently created first. As a trigger it starts the flow whenever a contact is edited (or added).';
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
        $contacts = $this->listAll($run, $config, '/contacts', 'contacts', [
            'listid' => is_numeric($config['list_id'] ?? null) ? (int) $config['list_id'] : null,
            'filters[updated_after]' => Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc()->format('Y-m-d H:i:s'),
            'orders[cdate]' => 'DESC',
        ], $this->limit($config, 25));

        return ['contacts' => array_map(fn (array $contact): array => $this->shapeContact($contact), $contacts), 'count' => count($contacts)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'contacts';
    }

    public function triggerItemId(array $item): string
    {
        return ($item['id'] ?? '').':'.($item['updated_at'] ?? '');
    }

    public function triggerName(): string
    {
        return 'ActiveCampaign: Contact updated';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is edited in ActiveCampaign.';
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
