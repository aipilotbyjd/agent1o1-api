<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MondayCreateSubitemNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_create_subitem';
    }

    public function name(): string
    {
        return 'monday.com: Create Subitem';
    }

    public function description(): string
    {
        return 'Adds a subitem under an item.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['item_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'item_id' => Field::text('Parent item', 'The item id, from Create Item or List Items.'),
                'name' => Field::text('Subitem name'),
                'column_values' => Field::advanced(Field::json('Column values (JSON)', 'By the subitem board\'s column ids.')),
                'create_labels' => Field::advanced(Field::boolean('Create missing labels', 'Add a status or dropdown label that does not exist yet.', false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $subitem = (array) ($this->monday($run, $config, 'mutation ($parent: ID!, $name: String!, $values: JSON, $labels: Boolean) { create_subitem(parent_item_id: $parent, item_name: $name, column_values: $values, create_labels_if_missing: $labels) { id name board { id } } }', [
            'parent' => $this->numericId($config, 'item_id', 'The item'),
            'name' => $this->requiredString($config, 'name', 'The subitem name'),
            'values' => $this->columnValues($config, required: false),
            'labels' => (bool) ($config['create_labels'] ?? false),
        ])['create_subitem'] ?? []);

        return ['id' => isset($subitem['id']) ? (string) $subitem['id'] : null, 'name' => $subitem['name'] ?? null, 'board_id' => isset($subitem['board']['id']) ? (string) $subitem['board']['id'] : null];
    }
}
