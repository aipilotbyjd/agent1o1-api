<?php

namespace App\Nodes\Integrations\GoogleSheets\Concerns;

/**
 * Turns a sheet's values into trigger items: one per data row, keyed by the
 * header row, with `_row` (the row's number in the sheet).
 *
 * Only the last rows are kept. A poll looks at no more than
 * `triggers.poll_max_items` items, taken from the front of the list, so a
 * sheet of 5,000 rows would never get past its first hundred and a row added
 * at the bottom would never start anything.
 */
trait ReadsRowsAsTriggerItems
{
    /**
     * @param  array<string, mixed>  $output  a values.get answer
     * @return list<array<string, mixed>>
     */
    protected function rowItems(array $output, bool $applyCap = true): array
    {
        $values = array_values((array) ($output['values'] ?? []));
        $header = array_map('strval', (array) array_shift($values));
        $firstRow = preg_match('/![A-Z]+(\d+)/', (string) ($output['range'] ?? ''), $match) ? (int) $match[1] : 1;

        $rows = [];

        foreach ($values as $index => $row) {
            $row = (array) $row;
            $item = ['_row' => $firstRow + $index + 1];

            foreach ($header as $column => $name) {
                $item[$name !== '' ? $name : 'column_'.($column + 1)] = $row[$column] ?? null;
            }

            $rows[] = $item;
        }

        return $applyCap ? $this->recentRows($rows) : $rows;
    }

    /**
     * The last rows only: as many as one poll looks at. A poll examines the
     * first `poll_max_items` items of what it is given, so a longer list would
     * hide the newest rows behind older ones it has already seen.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function recentRows(array $rows): array
    {
        $keep = max(1, (int) config('triggers.poll_max_items'));

        return count($rows) > $keep ? array_slice($rows, -$keep) : $rows;
    }
}
