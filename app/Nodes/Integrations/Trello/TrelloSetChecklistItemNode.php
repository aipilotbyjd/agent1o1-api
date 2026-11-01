<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloSetChecklistItemNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_set_checklist_item';
    }

    public function name(): string
    {
        return 'Trello: Tick or Untick Checklist Item';
    }

    public function description(): string
    {
        return 'Marks a checklist item done or not done.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['card_id', 'item_id'],
            'properties' => [
                ...$this->credentialFields(),
                'card_id' => Field::text('Card', 'The card id, from Create Card or List Cards.', '5f1c2b3a4d5e6f7a8b9c0d1e'),
                'item_id' => Field::text('Item id', 'From Get Card.'),
                'state' => Field::select('Mark as', ['complete' => 'Done', 'incomplete' => 'Not done'], null, 'complete'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->id($config, 'card_id', 'The card');
        $item = $this->id($config, 'item_id', 'The item');
        $state = ($config['state'] ?? 'complete') === 'incomplete' ? 'incomplete' : 'complete';

        $this->trello($run, $config, 'PUT', "/cards/{$id}/checkItem/{$item}", [], ['state' => $state]);

        return ['card_id' => $id, 'item_id' => $item, 'done' => $state === 'complete'];
    }
}
