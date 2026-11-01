<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloCreateChecklistNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_create_checklist';
    }

    public function name(): string
    {
        return 'Trello: Add Checklist to Card';
    }

    public function description(): string
    {
        return 'Adds a checklist to a card, with its first items.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['card_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'card_id' => Field::text('Card', 'The card id, from Create Card or List Cards.', '5f1c2b3a4d5e6f7a8b9c0d1e'),
                'name' => Field::text('Name', null, 'Launch steps'),
                'items' => Field::list('Items', 'One item per row.', 'Write the email'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->id($config, 'card_id', 'The card');
        $items = array_values(array_filter(array_map(fn (mixed $item): string => trim((string) $item), (array) ($config['items'] ?? []))));
        $checklist = $this->trello($run, $config, 'POST', '/checklists', [], ['idCard' => $id, 'name' => $this->requiredString($config, 'name', 'The name')]);

        foreach ($items as $item) {
            $this->trello($run, $config, 'POST', '/checklists/'.$this->id($checklist, 'id', 'The checklist').'/checkItems', [], ['name' => $item]);
        }

        return ['id' => $checklist['id'] ?? null, 'name' => $checklist['name'] ?? null, 'card_id' => $id, 'items_added' => count($items)];
    }
}
