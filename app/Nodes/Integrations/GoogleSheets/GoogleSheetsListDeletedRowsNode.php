<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Contracts\KeepsTriggerState;
use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsRemovalTrigger;
use App\Nodes\Integrations\GoogleSheets\Concerns\ReadsRowsAsTriggerItems;
use App\Nodes\Support\Field;
use App\Services\Triggers\PresenceTracker;
use InvalidArgumentException;

/**
 * Reads the rows of a range as records. As a trigger it fires when a row is
 * deleted.
 *
 * Sheets keeps no record of deleted rows, and a row's number changes when a
 * row above it goes, so a deletion can only be seen by a column that gives
 * each row its own value (an order number, an email): the trigger keeps those
 * values and reports the ones that are no longer in the sheet. A row whose id
 * is edited therefore counts as deleted (and appears as a new row to the
 * "added or updated" trigger).
 */
class GoogleSheetsListDeletedRowsNode extends AbstractGoogleSheetsNode implements KeepsTriggerState, SupportsTriggerMode
{
    use ActsAsRemovalTrigger;
    use ReadsRowsAsTriggerItems;

    public function type(): string
    {
        return 'google_sheets_list_deleted_rows';
    }

    public function name(): string
    {
        return 'Google Sheets: List Rows by Id';
    }

    public function description(): string
    {
        return 'Reads the rows of a range as records keyed by the header row. As a trigger it fires when a row is deleted, found by the row\'s id column.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['spreadsheet_id', 'range', 'id_column'],
            'properties' => [
                ...$this->credentialFields(),
                'spreadsheet_id' => Field::dynamic('Spreadsheet', 'google_sheets.spreadsheets', 'Pick a spreadsheet, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp'),
                'range' => Field::dynamic('Sheet / range', 'google_sheets.sheets', 'Pick a sheet tab to use the whole sheet, or type an A1 range such as Sheet1!A1:D10. The first row must hold the column names.', 'Sheet1', dependsOn: ['spreadsheet_id']),
                'id_column' => Field::text('Id column', 'The column name (from the first row) whose value is different on every row, such as "Order ID". A row counts as deleted when its value is gone from the sheet.', 'Order ID'),
            ],
        ];
    }

    /**
     * The rows as records, with `_row` and `_id` (the row's id column value).
     */
    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->rowItems($this->get($run, $this->valuesPath($config), $config), applyCap: false);

        return ['rows' => $this->withIds($rows, $this->idColumn($config))];
    }

    /**
     * The rows that were there last poll and are gone now.
     */
    public function pollSince(Run $run, array $config, ?array $state): array
    {
        $rows = $this->execute($run, $config, [])['rows'];

        $present = [];

        foreach ($rows as $row) {
            if (($row['_id'] ?? '') !== '') {
                $present[$row['_id']] = $row['_id'];
            }
        }

        $result = PresenceTracker::track($present, $state);

        return [
            'items' => array_map(fn (array $item): array => [...$item, 'id_column' => $this->idColumn($config), 'spreadsheet_id' => $config['spreadsheet_id'] ?? null, 'range' => $config['range'] ?? null], $result['items']),
            'state' => $result['state'],
        ];
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Sheets: Row deleted';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a row is deleted from the sheet, found by the value in its id column.';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function idColumn(array $config): string
    {
        $column = trim((string) ($config['id_column'] ?? ''));

        if ($column === '') {
            throw new InvalidArgumentException('Say which column holds each row\'s id ("Id column").');
        }

        return $column;
    }

    /**
     * Tags each row with the value of its id column, trimmed. A sheet with no
     * such column is an error, not a sheet whose rows were all deleted.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withIds(array $rows, string $column): array
    {
        if ($rows !== [] && ! array_key_exists($column, $rows[0])) {
            throw new InvalidArgumentException("The sheet has no column named \"{$column}\". Check the first row.");
        }

        return array_map(function (array $row) use ($column): array {
            $value = $row[$column] ?? null;

            return [...$row, '_id' => is_scalar($value) ? trim((string) $value) : ''];
        }, $rows);
    }
}
