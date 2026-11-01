<?php

namespace App\Nodes\Integrations\Airtable;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AirtableFindRecordNode extends AbstractAirtableNode
{
    public function type(): string
    {
        return 'airtable_find_record';
    }

    public function name(): string
    {
        return 'Airtable: Find Record';
    }

    public function description(): string
    {
        return 'Finds the first record whose field equals a value. Says whether one was found.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['base_id', 'table', 'field', 'value'],
            'properties' => [
                ...$this->credentialFields(),
                'base_id' => $this->baseField(),
                'table' => $this->tableField(),
                'field' => Field::text('Field', 'The field to look in, e.g. Email.'),
                'value' => Field::text('Equals'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $field = $this->formulaField((string) ($config['field'] ?? ''));
        $value = (string) ($config['value'] ?? '');
        $records = $this->listRecords($run, $config, ['filterByFormula' => "{$field} = ".$this->formulaText($value), 'maxRecords' => 1], 1);

        return $records === [] ? ['found' => false] : [...$this->shapeRecord($records[0]), 'found' => true];
    }
}
