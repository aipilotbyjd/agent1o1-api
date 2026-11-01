<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Integrations\GoogleSheets\Concerns\ReadsRowsAsTriggerItems;
use App\Nodes\Support\Field;

class GoogleSheetsListUpdatedRowsNode extends AbstractGoogleSheetsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;
    use ReadsRowsAsTriggerItems;

    public function type(): string
    {
        return 'google_sheets_list_updated_rows';
    }

    public function name(): string
    {
        return 'Google Sheets: List Rows With Changes';
    }

    public function description(): string
    {
        return 'Reads the rows of a range as records keyed by the header row. As a trigger it fires when a row is added or edited.';
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
                'range' => Field::dynamic('Sheet / range', 'google_sheets.sheets', 'Pick a sheet tab to use the whole sheet, or type an A1 range such as Sheet1!A1:D10. The first row must hold the column names.', 'Sheet1', dependsOn: ['spreadsheet_id']),
            ],
        ];
    }

    /**
     * The rows as records, with `_row` and `_version` (a fingerprint of the
     * row's cells, which changes when any cell does).
     */
    public function execute(Run $run, array $config, array $context): array
    {
        $output = $this->get($run, $this->valuesPath($config), $config);

        return ['rows' => $this->versioned($this->rowItems($output, applyCap: false))];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'rows';
    }

    /**
     * Only the last rows, as the trigger reads a whole sheet but acts on its tail.
     */
    public function triggerItems(array $output, array $config): array
    {
        return $this->recentRows(array_values(array_filter((array) ($output['rows'] ?? []), 'is_array')));
    }

    /**
     * A row is a new item each time its cells change: its number and version.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['_row']) ? $item['_row'].':'.($item['_version'] ?? '') : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Sheets: Row added or updated';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a row is added to the sheet or a cell in a row is edited.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function versioned(array $rows): array
    {
        return array_map(function (array $row): array {
            $cells = array_diff_key($row, ['_row' => true]);

            return [...$row, '_version' => substr(md5(json_encode($cells, JSON_THROW_ON_ERROR)), 0, 12)];
        }, $rows);
    }
}
