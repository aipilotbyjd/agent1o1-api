<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloGetCardNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_get_card';
    }

    public function name(): string
    {
        return 'Trello: Get Card';
    }

    public function description(): string
    {
        return 'Reads a card with its labels, checklists and attachment count.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['card_id'],
            'properties' => [
                ...$this->credentialFields(),
                'card_id' => Field::text('Card', 'The card id, from Create Card or List Cards.', '5f1c2b3a4d5e6f7a8b9c0d1e'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $card = $this->trello($run, $config, 'GET', '/cards/'.$this->id($config, 'card_id', 'The card'), ['checklists' => 'all', 'attachments' => 'true', 'attachment_fields' => 'name,url']);

        return [
            ...$this->shapeCard($card),
            'attachments' => array_values(array_map(fn (array $file): array => ['name' => $file['name'] ?? null, 'url' => $file['url'] ?? null], array_filter((array) ($card['attachments'] ?? []), 'is_array'))),
            'checklists' => array_values(array_map(fn (array $list): array => ['id' => $list['id'] ?? null, 'name' => $list['name'] ?? null, 'items' => array_values(array_map(fn (array $item): array => ['id' => $item['id'] ?? null, 'name' => $item['name'] ?? null, 'done' => ($item['state'] ?? '') === 'complete'], array_filter((array) ($list['checkItems'] ?? []), 'is_array')))], array_filter((array) ($card['checklists'] ?? []), 'is_array'))),
        ];
    }
}
