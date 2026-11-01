<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleSheetsUpdateValuesNode extends AbstractGoogleSheetsNode
{
    public function type(): string
    {
        return 'google_sheets_update_values';
    }

    public function name(): string
    {
        return 'Google Sheets: Update Values';
    }

    public function description(): string
    {
        return 'Overwrites the values in a spreadsheet range.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['spreadsheet_id', 'range', 'values'],
            'properties' => [
                ...$this->credentialFields(),
                'spreadsheet_id' => Field::dynamic('Spreadsheet', 'google_sheets.spreadsheets', 'Pick a spreadsheet, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp'),
                'range' => Field::dynamic('Sheet / range', 'google_sheets.sheets', 'Pick a sheet tab to use the whole sheet, or type an A1 range such as Sheet1!A1:D10.', 'Sheet1!A1:D10', dependsOn: ['spreadsheet_id']),
                'values' => Field::list('Row values', 'One row of cell values, left to right (column A first).', 'Value'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $endpoint = $this->valuesPath($config)
            .'?valueInputOption=USER_ENTERED';

        return $this->put($run, $endpoint, $config, [
            'values' => [$config['values']],
        ]);
    }
}
