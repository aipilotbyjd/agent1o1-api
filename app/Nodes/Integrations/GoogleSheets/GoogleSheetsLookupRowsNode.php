<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleSheetsLookupRowsNode extends AbstractGoogleSheetsNode
{
    public function type(): string
    {
        return 'google_sheets_lookup_rows';
    }

    public function name(): string
    {
        return 'Google Sheets: Lookup Rows';
    }

    public function description(): string
    {
        return 'Finds the rows whose value in a column matches, using the first row as column names.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['spreadsheet_id', 'range', 'column', 'value'],
            'properties' => [
                ...$this->credentialFields(),
                'spreadsheet_id' => Field::dynamic('Spreadsheet', 'google_sheets.spreadsheets', 'Pick a spreadsheet, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp'),
                'range' => Field::dynamic('Sheet / range', 'google_sheets.sheets', 'The tab to search. Its first row must hold the column names.', 'Sheet1', dependsOn: ['spreadsheet_id']),
                'column' => Field::text('Column name', 'Exactly as written in the first row.', 'Email'),
                'value' => Field::text('Value to find', 'Matches the whole cell, ignoring upper and lower case.', 'ada@example.com'),
                'max_results' => Field::integer('Max rows', 'Stop after this many matches.', 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $values = array_values((array) ($this->get($run, $this->valuesPath($config), $config)['values'] ?? []));
        $header = array_map('strval', (array) array_shift($values));
        $column = array_search((string) $config['column'], $header, true);

        if ($column === false) {
            return ['rows' => [], 'count' => 0, 'column_found' => false];
        }

        $needle = mb_strtolower(trim((string) $config['value']));
        $limit = max(1, (int) ($config['max_results'] ?? 100));
        $rows = [];

        foreach ($values as $index => $cells) {
            $cells = (array) $cells;

            if (mb_strtolower(trim((string) ($cells[$column] ?? ''))) !== $needle) {
                continue;
            }

            // The header is row 1 and `$index` is zero-based after removing it.
            $row = ['_row' => $index + 2];

            foreach ($header as $position => $name) {
                $row[$name !== '' ? $name : 'column_'.($position + 1)] = $cells[$position] ?? null;
            }

            $rows[] = $row;

            if (count($rows) >= $limit) {
                break;
            }
        }

        return ['rows' => $rows, 'count' => count($rows), 'column_found' => true];
    }
}
