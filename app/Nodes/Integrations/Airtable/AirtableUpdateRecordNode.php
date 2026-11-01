<?php

namespace App\Nodes\Integrations\Airtable;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AirtableUpdateRecordNode extends AbstractAirtableNode
{
    public function type(): string
    {
        return 'airtable_update_record';
    }

    public function name(): string
    {
        return 'Airtable: Update Record';
    }

    public function description(): string
    {
        return 'Changes some fields of a record. The fields you leave out keep their values.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['base_id', 'table', 'record_id', 'fields'],
            'properties' => [
                ...$this->credentialFields(),
                'base_id' => $this->baseField(),
                'table' => $this->tableField(),
                'record_id' => Field::text('Record', 'The record id (starts with rec), from a previous step.', 'recAbc123def456'),
                'fields' => Field::json('Fields (JSON)', 'Field names and values, e.g. {"Name": "Ada", "Status": "Todo"}.'),
                'typecast' => Field::advanced(Field::boolean('Convert values', 'Let Airtable turn text into the field\'s type (a date, a number, a new select option).', true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $record = $this->airtable($run, $config, 'PATCH', $this->tablePath($config).'/'.$this->recordId($config), [], ['fields' => $this->fields($config), 'typecast' => (bool) ($config['typecast'] ?? true)]);

        return $this->shapeRecord($record);
    }
}
