<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleSheetsClearRangeNode extends AbstractGoogleSheetsNode
{
    public function type(): string
    {
        return 'google_sheets_clear_range';
    }

    public function name(): string
    {
        return 'Google Sheets: Clear Range';
    }

    public function description(): string
    {
        return 'Empties the values in a range. Formatting is kept.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['spreadsheet_id', 'range'],
            'properties' => [
                ...$this->credentialFields(),
                'spreadsheet_id' => Field::dynamic('Spreadsheet', 'google_sheets.spreadsheets', 'Pick a spreadsheet, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp'),
                'range' => Field::dynamic('Sheet / range', 'google_sheets.sheets', 'Pick a sheet tab to clear the whole sheet, or type an A1 range such as Sheet1!A2:D100.', 'Sheet1!A2:D100', dependsOn: ['spreadsheet_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, $this->valuesPath($config).':clear', $config);
    }
}
