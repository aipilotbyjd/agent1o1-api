<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Workflows\ConditionEvaluator;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Works on rows (a list of objects): remove duplicates, group and total,
 * sort, keep or rename columns, filter, limit. One operation per step.
 */
class TableNode extends PureDataNode
{
    private const array OPERATIONS = [
        'dedupe' => 'Remove duplicate rows',
        'group' => 'Group and total',
        'sort' => 'Sort',
        'filter' => 'Keep rows that match',
        'select' => 'Keep only some columns',
        'rename' => 'Rename columns',
        'limit' => 'Take the first rows',
    ];

    private const int MAX_ROWS = 100000;

    public function type(): string
    {
        return 'table';
    }

    public function name(): string
    {
        return 'Rows';
    }

    public function icon(): string
    {
        return 'layers-01';
    }

    public function description(): string
    {
        return 'Works on rows: remove duplicates, group and total, sort, filter, keep or rename columns, or take the first few.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['operation', 'rows'],
            'properties' => [
                'operation' => Field::select('Operation', self::OPERATIONS, null, 'dedupe'),
                'rows' => Field::json('Rows', 'A list of objects, usually {{ ... }} from an earlier step.', 'array'),
                'columns' => Field::list('Columns', 'Remove duplicates: the columns that identify a duplicate (empty means the whole row). Group: the columns to group by. Keep only some columns: the ones to keep. Sort: the columns to sort by.', 'email'),
                'aggregates' => Field::keyValue('Totals', 'Group only. Result name on the left; what to compute on the right: count, or sum:column, avg:column, min:column, max:column. E.g. orders → count, revenue → sum:amount.'),
                'direction' => Field::select('Order', ['asc' => 'Smallest first', 'desc' => 'Largest first'], 'Sort: which way.', 'asc'),
                'column' => Field::text('Column', 'Keep rows that match: the column to test.', 'status'),
                'condition' => Field::select('Condition', array_combine(['equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with', 'greater_than', 'greater_or_equal', 'less_than', 'less_or_equal', 'in_list', 'matches_regex', 'is_empty', 'is_not_empty'], ['Equals', 'Does not equal', 'Contains', 'Does not contain', 'Starts with', 'Ends with', 'Greater than', 'Greater than or equal to', 'Less than', 'Less than or equal to', 'Is one of (comma-separated)', 'Matches pattern (regex)', 'Is empty', 'Is not empty']), 'Keep rows that match.', 'equals'),
                'value' => Field::text('Compare to', 'Keep rows that match: the value to compare with.'),
                'mapping' => Field::keyValue('New names', 'Rename columns: old name on the left, new name on the right.'),
                'count' => Field::integer('How many', 'Take the first rows: how many to keep.', 10, 0, self::MAX_ROWS),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'rows' => ['title' => 'Rows', 'description' => 'The resulting rows.'],
                'row_count' => ['title' => 'Row count', 'description' => 'How many rows there are now.'],
                'removed' => ['title' => 'Removed', 'description' => 'How many rows were dropped (duplicates removed, rows filtered out or cut).'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->rows($config['rows'] ?? null);
        $columns = $this->strings($config['columns'] ?? []);
        $operation = (string) ($config['operation'] ?? '');

        $result = match ($operation) {
            'dedupe' => $this->dedupe($rows, $columns),
            'group' => $this->group($rows, $columns, $config['aggregates'] ?? []),
            'sort' => $this->sort($rows, $columns, ($config['direction'] ?? 'asc') === 'desc'),
            'filter' => $this->filter($rows, $config),
            'select' => $this->select($rows, $columns),
            'rename' => $this->rename($rows, is_array($config['mapping'] ?? null) ? $config['mapping'] : []),
            'limit' => array_slice($rows, 0, max(0, (int) ($config['count'] ?? 10))),
            default => throw new InvalidArgumentException("Unsupported rows operation [{$operation}]."),
        };

        return ['rows' => $result, 'row_count' => count($result), 'removed' => in_array($operation, ['dedupe', 'filter', 'limit'], true) ? count($rows) - count($result) : 0];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException('"Rows" must be a list of objects.');
        }

        if (count($value) > self::MAX_ROWS) {
            throw new InvalidArgumentException('There are too many rows to process (over '.number_format(self::MAX_ROWS).').');
        }

        return array_map(fn (mixed $row): array => is_array($row) ? $row : ['value' => $row], $value);
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter(array_map(fn (mixed $item): string => trim((string) $item), $value), fn (string $item): bool => $item !== '')) : [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $columns
     */
    private function key(array $row, array $columns): string
    {
        $parts = $columns === [] ? $row : array_map(fn (string $column): mixed => data_get($row, $column), $columns);

        return (string) json_encode($parts, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function dedupe(array $rows, array $columns): array
    {
        $seen = [];
        $kept = [];

        foreach ($rows as $row) {
            $key = $this->key($row, $columns);

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $kept[] = $row;
            }
        }

        return $kept;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function group(array $rows, array $columns, mixed $aggregates): array
    {
        if ($columns === []) {
            throw new InvalidArgumentException('Choose at least one column to group by.');
        }

        $computations = [];

        foreach (is_array($aggregates) ? $aggregates : [] as $name => $spec) {
            $name = trim((string) $name);
            [$function, $column] = array_pad(explode(':', trim((string) $spec), 2), 2, '');

            if ($name === '' || ! in_array(strtolower(trim($function)), ['count', 'sum', 'avg', 'min', 'max'], true) || (strtolower(trim($function)) !== 'count' && trim($column) === '')) {
                throw new InvalidArgumentException("The total “{$name}” needs to be count, or sum:column, avg:column, min:column or max:column.");
            }

            $computations[$name] = [strtolower(trim($function)), trim($column)];
        }

        $groups = [];

        foreach ($rows as $row) {
            $key = $this->key($row, $columns);
            $groups[$key]['rows'][] = $row;
            $groups[$key]['keys'] ??= array_combine($columns, array_map(fn (string $column): mixed => data_get($row, $column), $columns));
        }

        return array_values(array_map(function (array $group) use ($computations): array {
            $result = $group['keys'];

            foreach ($computations as $name => [$function, $column]) {
                $values = array_values(array_filter(array_map(fn (array $row): mixed => data_get($row, $column), $group['rows']), fn (mixed $value): bool => is_numeric($value)));

                $result[$name] = match ($function) {
                    'count' => count($group['rows']),
                    'sum' => array_sum($values),
                    'avg' => $values === [] ? null : round(array_sum($values) / count($values), 4),
                    'min' => $values === [] ? null : min($values),
                    'max' => $values === [] ? null : max($values),
                };
            }

            return $result;
        }, $groups));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function sort(array $rows, array $columns, bool $descending): array
    {
        if ($columns === []) {
            throw new InvalidArgumentException('Choose at least one column to sort by.');
        }

        usort($rows, function (array $a, array $b) use ($columns, $descending): int {
            foreach ($columns as $column) {
                $left = data_get($a, $column);
                $right = data_get($b, $column);
                $order = is_numeric($left) && is_numeric($right) ? $left <=> $right : strnatcasecmp((string) $left, (string) $right);

                if ($order !== 0) {
                    return $descending ? -$order : $order;
                }
            }

            return 0;
        });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    private function filter(array $rows, array $config): array
    {
        $column = trim((string) ($config['column'] ?? ''));
        $operator = (string) ($config['condition'] ?? 'equals');

        if ($column === '') {
            throw new InvalidArgumentException('Say which column to test.');
        }

        if (! ConditionEvaluator::isKnown($operator)) {
            throw new InvalidArgumentException("Unsupported condition [{$operator}].");
        }

        return array_values(array_filter($rows, fn (array $row): bool => ConditionEvaluator::matches(data_get($row, $column), $operator, $config['value'] ?? null)));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function select(array $rows, array $columns): array
    {
        if ($columns === []) {
            throw new InvalidArgumentException('Choose at least one column to keep.');
        }

        return array_map(fn (array $row): array => array_combine($columns, array_map(fn (string $column): mixed => Arr::get($row, $column), $columns)), $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $mapping
     * @return list<array<string, mixed>>
     */
    private function rename(array $rows, array $mapping): array
    {
        if ($mapping === []) {
            throw new InvalidArgumentException('Add at least one column to rename.');
        }

        return array_map(function (array $row) use ($mapping): array {
            $renamed = [];

            foreach ($row as $key => $value) {
                $renamed[(string) ($mapping[$key] ?? $key)] = $value;
            }

            return $renamed;
        }, $rows);
    }
}
