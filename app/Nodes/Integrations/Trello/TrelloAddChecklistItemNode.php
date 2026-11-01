<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloAddChecklistItemNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_add_checklist_item';
    }

    public function name(): string
    {
        return 'Trello: Add Checklist Item';
    }

    public function description(): string
    {
        return 'Adds an item to a checklist.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['checklist_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'checklist_id' => Field::text('Checklist id', 'From Add Checklist to Card or Get Card.'),
                'name' => Field::text('Item'),
                'checked' => Field::advanced(Field::boolean('Already done', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->id($config, 'checklist_id', 'The checklist');
        $item = $this->trello($run, $config, 'POST', "/checklists/{$id}/checkItems", [], ['name' => $this->requiredString($config, 'name', 'The item'), 'checked' => (bool) ($config['checked'] ?? false)]);

        return ['id' => $item['id'] ?? null, 'name' => $item['name'] ?? null, 'done' => ($item['state'] ?? '') === 'complete'];
    }
}
