<?php

namespace App\Nodes\Integrations\Airtable;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AirtableListRecordsNode extends AbstractAirtableNode
{
    public function type(): string
    {
        return 'airtable_list_records';
    }

    public function name(): string
    {
        return 'Airtable: List Records';
    }

    public function description(): string
    {
        return 'Reads records of a table, optionally from a view, matching a formula, sorted, and with only some fields.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['base_id', 'table'],
            'properties' => [
                ...$this->credentialFields(),
                'base_id' => $this->baseField(),
                'table' => $this->tableField(),
                'view' => Field::advanced(Field::text('View', 'A view\'s name or id. Its filters and order apply.')),
                'formula' => Field::advanced(Field::text('Only records where', 'An Airtable formula, e.g. {Status} = \'Todo\'.')),
                'sort_field' => Field::advanced(Field::text('Sort by field')),
                'sort_direction' => Field::advanced(Field::select('Order', ['asc' => 'A to Z, oldest first', 'desc' => 'Z to A, newest first'], null, 'asc')),
                'only_fields' => Field::advanced(Field::list('Only these fields', 'One field name per row. Empty returns all.')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $only = array_values(array_filter(array_map(fn (mixed $name): string => trim((string) $name), (array) ($config['only_fields'] ?? []))));
        $records = $this->listRecords($run, $config, [
            'view' => filled($config['view'] ?? null) ? trim((string) $config['view']) : null,
            'filterByFormula' => filled($config['formula'] ?? null) ? trim((string) $config['formula']) : null,
            'sort[0][field]' => filled($config['sort_field'] ?? null) ? trim((string) $config['sort_field']) : null,
            'sort[0][direction]' => filled($config['sort_field'] ?? null) ? (($config['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc') : null,
            'fields' => $only === [] ? null : $only,
        ], $this->limit($config, 50));

        return ['records' => array_map($this->shapeRecord(...), $records), 'count' => count($records)];
    }
}
