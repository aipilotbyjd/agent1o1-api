<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleSheetsAddSheetNode extends AbstractGoogleSheetsNode
{
    public function type(): string
    {
        return 'google_sheets_add_sheet';
    }

    public function name(): string
    {
        return 'Google Sheets: Add Sheet Tab';
    }

    public function description(): string
    {
        return 'Adds a new tab to an existing spreadsheet.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['spreadsheet_id', 'title'],
            'properties' => [
                ...$this->credentialFields(),
                'spreadsheet_id' => Field::dynamic('Spreadsheet', 'google_sheets.spreadsheets', 'Pick a spreadsheet, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp'),
                'title' => Field::text('Tab name', 'Must not match an existing tab.', 'April'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->post($run, $this->spreadsheetPath($config).':batchUpdate', $config, [
            'requests' => [['addSheet' => ['properties' => ['title' => $config['title']]]]],
        ]);

        $properties = $response['replies'][0]['addSheet']['properties'] ?? [];

        return [
            'sheet_id' => $properties['sheetId'] ?? null,
            'title' => $properties['title'] ?? $config['title'],
            'index' => $properties['index'] ?? null,
        ];
    }
}
