<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class MondayGetItemNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_get_item';
    }

    public function name(): string
    {
        return 'monday.com: Get Item';
    }

    public function description(): string
    {
        return 'Reads one item with its column values, keyed by column title and by id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['item_id'],
            'properties' => [
                ...$this->credentialFields(),
                'item_id' => Field::text('Item', 'The item id, from Create Item or List Items.', '1234567890'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = $this->monday($run, $config, 'query ($ids: [ID!]) { items(ids: $ids) { '.self::ITEM_FIELDS.' } }', ['ids' => [$this->numericId($config, 'item_id', 'The item')]]);
        $item = (array) (($found['items'] ?? [])[0] ?? throw new RuntimeException('monday.com error: that item was not found, or the token cannot see it.'));

        return $this->shapeItem($item);
    }
}
