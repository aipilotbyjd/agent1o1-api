<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MondayArchiveItemNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_archive_item';
    }

    public function name(): string
    {
        return 'monday.com: Archive Item';
    }

    public function description(): string
    {
        return 'Archives an item. It can be restored from the board\'s archive for 30 days.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        $id = $this->numericId($config, 'item_id', 'The item');

        $this->monday($run, $config, 'mutation ($item: ID!) { archive_item(item_id: $item) { id } }', ['item' => $id]);

        return ['archived' => true, 'item_id' => $id];
    }
}
