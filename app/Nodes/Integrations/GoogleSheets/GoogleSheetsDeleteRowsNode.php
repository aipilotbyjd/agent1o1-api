<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleSheetsDeleteRowsNode extends AbstractGoogleSheetsNode
{
    public function type(): string
    {
        return 'google_sheets_delete_rows';
    }

    public function name(): string
    {
        return 'Google Sheets: Delete Rows';
    }

    public function description(): string
    {
        return 'Removes rows from a tab and shifts the rows below up.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['spreadsheet_id', 'range', 'start_row'],
            'properties' => [
                ...$this->credentialFields(),
                'spreadsheet_id' => Field::dynamic('Spreadsheet', 'google_sheets.spreadsheets', 'Pick a spreadsheet, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp'),
                'range' => Field::dynamic('Sheet', 'google_sheets.sheets', 'The tab to delete rows from.', 'Sheet1', dependsOn: ['spreadsheet_id']),
                'start_row' => Field::integer('First row to delete', 'Row number as shown in the sheet (row 1 is the header).', 2, 1),
                'row_count' => Field::integer('How many rows', 'Rows removed starting at the first row.', 1, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $startRow = max(1, (int) $config['start_row']);
        $rowCount = max(1, (int) ($config['row_count'] ?? 1));

        $this->post($run, $this->spreadsheetPath($config).':batchUpdate', $config, [
            'requests' => [[
                'deleteDimension' => ['range' => [
                    'sheetId' => $this->sheetIdFor($run, $config, (string) $config['range']),
                    'dimension' => 'ROWS',
                    // The API is zero-based and the end index is exclusive.
                    'startIndex' => $startRow - 1,
                    'endIndex' => $startRow - 1 + $rowCount,
                ]],
            ]],
        ]);

        return ['deleted_rows' => $rowCount, 'start_row' => $startRow];
    }
}
