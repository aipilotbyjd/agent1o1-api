<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Integrations\GoogleSheets\Concerns\ReadsRowsAsTriggerItems;
use App\Nodes\Support\Field;

class GoogleSheetsGetValuesNode extends AbstractGoogleSheetsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;
    use ReadsRowsAsTriggerItems;

    public function type(): string
    {
        return 'google_sheets_get_values';
    }

    public function name(): string
    {
        return 'Google Sheets: Get Values';
    }

    public function description(): string
    {
        return 'Reads a range of cell values from a spreadsheet.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['spreadsheet_id', 'range'],
            'properties' => [
                ...$this->credentialFields(),
                'spreadsheet_id' => Field::dynamic('Spreadsheet', 'google_sheets.spreadsheets', 'Pick a spreadsheet, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp'),
                'range' => Field::dynamic('Sheet / range', 'google_sheets.sheets', 'Pick a sheet tab to use the whole sheet, or type an A1 range such as Sheet1!A1:D10.', 'Sheet1!A1:D10', dependsOn: ['spreadsheet_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get(
            $run,
            $this->valuesPath($config),
            $config,
        );
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return null;
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Sheets: New row';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a new row is added to the sheet.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 1;
    }

    /**
     * One item per data row, keyed by the header row, with `_row` (its row
     * number in the sheet) as its id: a trigger here fires on new rows.
     */
    public function triggerItems(array $output, array $config): array
    {
        return $this->rowItems($output);
    }

    /**
     * An item's stable id when used as a trigger.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['_row'] ?? '');
    }
}
