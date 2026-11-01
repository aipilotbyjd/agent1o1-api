<?php

namespace App\Nodes\Integrations\Airtable;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AirtableCreateRecordNode extends AbstractAirtableNode
{
    public function type(): string
    {
        return 'airtable_create_record';
    }

    public function name(): string
    {
        return 'Airtable: Create Record';
    }

    public function description(): string
    {
        return 'Adds a record to a table.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['base_id', 'table', 'fields'],
            'properties' => [
                ...$this->credentialFields(),
                'base_id' => $this->baseField(),
                'table' => $this->tableField(),
                'fields' => Field::json('Fields (JSON)', 'Field names and values, e.g. {"Name": "Ada", "Status": "Todo"}.'),
                'typecast' => Field::advanced(Field::boolean('Convert values', 'Let Airtable turn text into the field\'s type (a date, a number, a new select option).', true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $record = $this->airtable($run, $config, 'POST', $this->tablePath($config), [], ['fields' => $this->fields($config), 'typecast' => (bool) ($config['typecast'] ?? true)]);

        return $this->shapeRecord($record);
    }
}
