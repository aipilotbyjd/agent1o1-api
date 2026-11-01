<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleSheetsGetSpreadsheetInfoNode extends AbstractGoogleSheetsNode
{
    public function type(): string
    {
        return 'google_sheets_get_spreadsheet_info';
    }

    public function name(): string
    {
        return 'Google Sheets: Get Spreadsheet Info';
    }

    public function description(): string
    {
        return 'Returns a spreadsheet\'s title, link and the list of its tabs.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['spreadsheet_id'],
            'properties' => [
                ...$this->credentialFields(),
                'spreadsheet_id' => Field::dynamic('Spreadsheet', 'google_sheets.spreadsheets', 'Pick a spreadsheet, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $spreadsheet = $this->get($run, $this->spreadsheetPath($config), $config, [
            'fields' => 'spreadsheetId,spreadsheetUrl,properties(title,locale,timeZone),sheets.properties(sheetId,title,index,gridProperties(rowCount,columnCount))',
        ]);

        return [
            'spreadsheet_id' => $spreadsheet['spreadsheetId'] ?? $config['spreadsheet_id'],
            'title' => $spreadsheet['properties']['title'] ?? null,
            'url' => $spreadsheet['spreadsheetUrl'] ?? null,
            'locale' => $spreadsheet['properties']['locale'] ?? null,
            'time_zone' => $spreadsheet['properties']['timeZone'] ?? null,
            'sheets' => array_map(fn (array $sheet): array => [
                'sheet_id' => $sheet['properties']['sheetId'] ?? null,
                'title' => $sheet['properties']['title'] ?? null,
                'index' => $sheet['properties']['index'] ?? null,
                'row_count' => $sheet['properties']['gridProperties']['rowCount'] ?? null,
                'column_count' => $sheet['properties']['gridProperties']['columnCount'] ?? null,
            ], (array) ($spreadsheet['sheets'] ?? [])),
        ];
    }
}
