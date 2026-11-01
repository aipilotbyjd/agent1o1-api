<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MondayAddUpdateNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_add_update';
    }

    public function name(): string
    {
        return 'monday.com: Add Update';
    }

    public function description(): string
    {
        return 'Posts an update (a comment) on an item.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['item_id', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'item_id' => Field::text('Item', 'The item id, from Create Item or List Items.', '1234567890'),
                'text' => Field::textarea('Update', 'Plain text or simple HTML.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $update = (array) ($this->monday($run, $config, 'mutation ($item: ID!, $body: String!) { create_update(item_id: $item, body: $body) { id text_body created_at creator { name } } }', ['item' => $this->numericId($config, 'item_id', 'The item'), 'body' => $this->requiredString($config, 'text', 'The update')])['create_update'] ?? []);

        return ['id' => isset($update['id']) ? (string) $update['id'] : null, 'item_id' => trim((string) $config['item_id']), 'text' => $update['text_body'] ?? null, 'created_at' => $update['created_at'] ?? null, 'author' => data_get($update, 'creator.name')];
    }
}
