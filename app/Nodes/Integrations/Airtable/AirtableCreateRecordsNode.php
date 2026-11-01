<?php

namespace App\Nodes\Integrations\Airtable;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class AirtableCreateRecordsNode extends AbstractAirtableNode
{
    public function type(): string
    {
        return 'airtable_create_records';
    }

    public function name(): string
    {
        return 'Airtable: Create Many Records';
    }

    public function description(): string
    {
        return 'Adds several records to a table, ten per request.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['base_id', 'table', 'records'],
            'properties' => [
                ...$this->credentialFields(),
                'base_id' => $this->baseField(),
                'table' => $this->tableField(),
                'records' => Field::json('Records (JSON list)', 'A list of field objects, e.g. [{"Name": "Ada"}, {"Name": "Bob"}].', 'array'),
                'typecast' => Field::advanced(Field::boolean('Convert values', 'Let Airtable turn text into the field\'s type (a date, a number, a new select option).', true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $given = is_string($config['records'] ?? null) ? json_decode($config['records'], true) : ($config['records'] ?? null);

        if (! is_array($given) || $given === [] || ! array_is_list($given) || count($given) > 500) {
            throw new InvalidArgumentException('Give between 1 and 500 records as a JSON list of field objects.');
        }

        foreach ($given as $index => $fields) {
            if (! is_array($fields) || $fields === [] || array_is_list($fields)) {
                throw new InvalidArgumentException('Record '.($index + 1).' must be an object of fields.');
            }
        }

        $created = [];

        foreach (array_chunk($given, 10) as $chunk) {
            $result = $this->airtable($run, $config, 'POST', $this->tablePath($config), [], ['records' => array_map(fn (array $fields): array => ['fields' => $fields], $chunk), 'typecast' => (bool) ($config['typecast'] ?? true)]);
            array_push($created, ...array_values(array_filter((array) ($result['records'] ?? []), 'is_array')));
        }

        return ['records' => array_map($this->shapeRecord(...), $created), 'count' => count($created)];
    }
}
