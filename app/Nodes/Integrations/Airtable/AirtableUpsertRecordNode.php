<?php

namespace App\Nodes\Integrations\Airtable;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class AirtableUpsertRecordNode extends AbstractAirtableNode
{
    public function type(): string
    {
        return 'airtable_upsert_record';
    }

    public function name(): string
    {
        return 'Airtable: Create or Update Record';
    }

    public function description(): string
    {
        return 'Updates the record whose chosen fields match, or adds a new one if none does. Safe to run twice.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['base_id', 'table', 'fields', 'match_fields'],
            'properties' => [
                ...$this->credentialFields(),
                'base_id' => $this->baseField(),
                'table' => $this->tableField(),
                'fields' => Field::json('Fields (JSON)', 'Field names and values, e.g. {"Name": "Ada", "Status": "Todo"}.'),
                'match_fields' => Field::list('Match on', 'The field names that identify the record, e.g. Email. They must be in Fields.', 'Email'),
                'typecast' => Field::advanced(Field::boolean('Convert values', 'Let Airtable turn text into the field\'s type (a date, a number, a new select option).', true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = $this->fields($config);
        $match = array_values(array_unique(array_filter(array_map(fn (mixed $name): string => trim((string) $name), (array) ($config['match_fields'] ?? [])))));

        if ($match === [] || count($match) > 3) {
            throw new InvalidArgumentException('Give one to three fields to match on.');
        }

        foreach ($match as $name) {
            if (! array_key_exists($name, $fields)) {
                throw new InvalidArgumentException("The match field \"{$name}\" must also be in Fields.");
            }
        }

        $result = $this->airtable($run, $config, 'PATCH', $this->tablePath($config), [], ['records' => [['fields' => $fields]], 'typecast' => (bool) ($config['typecast'] ?? true), 'performUpsert' => ['fieldsToMergeOn' => $match]]);
        $record = (array) (($result['records'] ?? [])[0] ?? []);

        return [...$this->shapeRecord($record), 'created' => in_array($record['id'] ?? null, (array) ($result['createdRecords'] ?? []), true)];
    }
}
