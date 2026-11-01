<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class ActiveCampaignListContactsNode extends AbstractActiveCampaignNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'activecampaign_list_contacts';
    }

    public function name(): string
    {
        return 'ActiveCampaign: List Contacts';
    }

    public function description(): string
    {
        return 'Lists contacts, newest first, optionally of a list or tag, matching a search, or created or changed since a time. As a trigger it starts the flow for each new contact.';
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
                'tag_id' => Field::advanced(Field::dynamic('Only with tag', 'activecampaign.tags', null, 'customer', type: 'integer')),
                'search' => Field::advanced(Field::text('Search', 'Part of an email, name or phone.')),
                'created_after' => Field::advanced(Field::datetime('Created after')),
                'updated_after' => Field::advanced(Field::datetime('Changed after')),
                'limit' => Field::integer('Max results', null, 50, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $contacts = $this->listAll($run, $config, '/contacts', 'contacts', [
            'listid' => is_numeric($config['list_id'] ?? null) ? (int) $config['list_id'] : null,
            'tagid' => is_numeric($config['tag_id'] ?? null) ? (int) $config['tag_id'] : null,
            'search' => filled($config['search'] ?? null) ? (string) $config['search'] : null,
            'filters[created_after]' => filled($config['created_after'] ?? null) ? Carbon::parse((string) $config['created_after'])->utc()->format('Y-m-d H:i:s') : null,
            'filters[updated_after]' => filled($config['updated_after'] ?? null) ? Carbon::parse((string) $config['updated_after'])->utc()->format('Y-m-d H:i:s') : null,
            'orders[cdate]' => 'DESC',
        ], $this->limit($config, 50, 10000));

        return ['contacts' => array_map(fn (array $contact): array => $this->shapeContact($contact), $contacts), 'count' => count($contacts)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'contacts';
    }

    public function triggerName(): string
    {
        return 'ActiveCampaign: New contact';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is added in ActiveCampaign.';
    }

    /**
     * Those created in the look-back window.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'created_after' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'updated_after' => null, 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
