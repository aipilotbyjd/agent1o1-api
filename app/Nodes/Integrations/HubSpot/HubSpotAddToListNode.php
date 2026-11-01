<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class HubSpotAddToListNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_add_contacts_to_list';
    }

    public function name(): string
    {
        return 'HubSpot: Add Contacts to List';
    }

    public function description(): string
    {
        return 'Adds contacts to a static list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'contact_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => Field::text('List id', 'The number in the list\'s address. Only static lists can be changed.'),
                'contact_ids' => Field::list('Contacts', 'One contact id per row.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = $this->numericId($config, 'list_id', 'The list');
        $ids = $config['contact_ids'] ?? [];
        $ids = array_values(array_unique(array_map(fn (mixed $id): string => $this->numericId(['id' => $id], 'id', 'A contact'), array_filter(is_array($ids) ? $ids : preg_split('/[\\s,]+/', (string) $ids), fn (mixed $id): bool => trim((string) $id) !== ''))));

        if ($ids === [] || count($ids) > 100000) {
            throw new InvalidArgumentException('Give at least one contact id.');
        }

        foreach (array_chunk($ids, 100) as $chunk) {
            $this->hubspot($run, $config, 'PUT', "/crm/v3/lists/{$list}/memberships/add", [], $chunk);
        }

        return ['added' => count($ids), 'list_id' => $list];
    }
}
