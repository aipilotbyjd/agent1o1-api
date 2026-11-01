<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Workflows\NodeOptions\Sources\GoogleSheetsOptions;

class GoogleSheetsCreateSpreadsheetNode extends AbstractGoogleSheetsNode
{
    public function type(): string
    {
        return 'google_sheets_create_spreadsheet';
    }

    public function name(): string
    {
        return 'Google Sheets: Create Spreadsheet';
    }

    public function description(): string
    {
        return 'Creates a new spreadsheet, optionally with a header row.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title', 'The name of the new spreadsheet.', 'Weekly report'),
                'sheet_title' => Field::text('First tab name', 'Leave empty to use Google\'s default (Sheet1).', 'Sheet1'),
                'header' => Field::list('Header row', 'Column names written to row 1 of the first tab.', 'Column name'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $sheetTitle = trim((string) ($config['sheet_title'] ?? ''));

        $created = $this->post($run, '/spreadsheets', $config, array_filter([
            'properties' => ['title' => $config['title']],
            'sheets' => $sheetTitle !== '' ? [['properties' => ['title' => $sheetTitle]]] : null,
        ]));

        $header = array_values(array_filter((array) ($config['header'] ?? []), fn (mixed $cell): bool => $cell !== null && $cell !== ''));

        if ($header !== []) {
            $tab = self::tabName($created['sheets'][0]['properties']['title'] ?? 'Sheet1');

            $this->post($run, $this->valuesPath([
                'spreadsheet_id' => $created['spreadsheetId'],
                'range' => GoogleSheetsOptions::a1SheetName($tab).'!A1',
            ]).':append?valueInputOption=USER_ENTERED', $config, ['values' => [$header]]);
        }

        return [
            'spreadsheet_id' => $created['spreadsheetId'] ?? null,
            'url' => $created['spreadsheetUrl'] ?? null,
            'title' => $created['properties']['title'] ?? $config['title'],
        ];
    }
}
