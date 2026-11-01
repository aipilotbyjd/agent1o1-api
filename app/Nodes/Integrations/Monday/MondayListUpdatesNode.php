<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MondayListUpdatesNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_list_updates';
    }

    public function name(): string
    {
        return 'monday.com: List Updates';
    }

    public function description(): string
    {
        return 'Lists the updates (comments) on an item, newest first.';
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
                'limit' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = $this->monday($run, $config, 'query ($ids: [ID!], $limit: Int) { items(ids: $ids) { updates(limit: $limit) { id text_body created_at creator { name } } } }', ['ids' => [$this->numericId($config, 'item_id', 'The item')], 'limit' => $this->limit($config, 25, 100)]);
        $updates = array_values(array_filter((array) data_get($found, 'items.0.updates', []), 'is_array'));

        return ['updates' => array_map(fn (array $update): array => ['id' => isset($update['id']) ? (string) $update['id'] : null, 'text' => $update['text_body'] ?? null, 'created_at' => $update['created_at'] ?? null, 'author' => data_get($update, 'creator.name')], $updates), 'count' => count($updates)];
    }
}
