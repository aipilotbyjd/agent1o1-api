<?php

namespace App\Nodes\Integrations\Airtable;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class AirtableListTablesNode extends AbstractAirtableNode
{
    public function type(): string
    {
        return 'airtable_list_tables';
    }

    public function name(): string
    {
        return 'Airtable: List Tables';
    }

    public function description(): string
    {
        return 'Lists the tables of a base with their fields and views.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['base_id'],
            'properties' => [
                ...$this->credentialFields(),
                'base_id' => $this->baseField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = $this->airtable($run, $config, 'GET', '/meta/bases/'.$this->baseId($config).'/tables');
        $tables = array_values(array_filter((array) ($found['tables'] ?? []), 'is_array'));

        return ['tables' => array_map(fn (array $table): array => [
            'id' => $table['id'] ?? null,
            'name' => $table['name'] ?? null,
            'primary_field' => collect((array) ($table['fields'] ?? []))->firstWhere('id', $table['primaryFieldId'] ?? null)['name'] ?? null,
            'fields' => array_values(array_map(fn (array $field): array => ['id' => $field['id'] ?? null, 'name' => $field['name'] ?? null, 'type' => $field['type'] ?? null], array_filter((array) ($table['fields'] ?? []), 'is_array'))),
            'views' => array_values(array_map(fn (array $view): array => ['id' => $view['id'] ?? null, 'name' => $view['name'] ?? null, 'type' => $view['type'] ?? null], array_filter((array) ($table['views'] ?? []), 'is_array'))),
        ], $tables), 'count' => count($tables)];
    }
}
