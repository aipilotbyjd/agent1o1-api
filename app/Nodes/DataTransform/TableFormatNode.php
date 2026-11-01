<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Nodes\Support\FilePayload;
use InvalidArgumentException;

/**
 * Turns rows into text a person can read or a tool can open: a CSV file to
 * attach, a Markdown table for Slack or Notion, an HTML table for an email.
 */
class TableFormatNode extends PureDataNode
{
    private const array FORMATS = ['csv' => 'CSV', 'tsv' => 'TSV (tab separated)', 'markdown' => 'Markdown table', 'html' => 'HTML table'];

    private const int MAX_ROWS = 20000;

    public function type(): string
    {
        return 'table_format';
    }

    public function name(): string
    {
        return 'Rows to Table';
    }

    public function icon(): string
    {
        return 'table';
    }

    public function description(): string
    {
        return 'Turns rows into a CSV file, a Markdown table or an HTML table, to attach to an email or post in a message.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['rows', 'format'],
            'properties' => [
                'rows' => Field::json('Rows', 'A list of objects, usually {{ ... }} from an earlier step.', 'array'),
                'format' => Field::select('Format', self::FORMATS, null, 'csv'),
                'columns' => Field::list('Columns', 'Optional. Which columns to include, in this order. Leave empty for all of them.', 'email'),
                'filename' => Field::advanced(Field::text('File name', 'CSV and TSV only.', 'export.csv')),
                'protect_formulas' => Field::advanced(Field::boolean('Protect against spreadsheet formulas', 'Cells starting with = + - or @ are prefixed so Excel and Sheets do not run them. Turn off only for numbers you trust.', true)),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => ['title' => 'Table', 'description' => 'The table as text.'],
                'file' => ['title' => 'File', 'description' => 'CSV and TSV only: the table as a file to attach, e.g. {{ nodes.table.file }}.'],
                'row_count' => ['title' => 'Row count', 'description' => 'How many rows were written.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $config['rows'] ?? [];

        if (is_string($rows)) {
            $rows = json_decode($rows, true);
        }

        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new InvalidArgumentException('"Rows" must be a list of objects.');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new InvalidArgumentException('There are too many rows to format (over '.number_format(self::MAX_ROWS).').');
        }

        $rows = array_map(fn (mixed $row): array => is_array($row) ? $row : ['value' => $row], $rows);
        $columns = $this->columns($rows, $config);
        $format = (string) ($config['format'] ?? 'csv');
        $protect = (bool) ($config['protect_formulas'] ?? true);

        $text = match ($format) {
            'csv' => $this->delimited($rows, $columns, ',', $protect),
            'tsv' => $this->delimited($rows, $columns, "\t", $protect),
            'markdown' => $this->markdown($rows, $columns),
            'html' => $this->html($rows, $columns),
            default => throw new InvalidArgumentException("Unsupported format [{$format}]."),
        };

        $output = ['text' => $text, 'row_count' => count($rows)];

        if (in_array($format, ['csv', 'tsv'], true)) {
            $name = trim((string) ($config['filename'] ?? '')) ?: 'export.'.$format;
            $output['file'] = FilePayload::make($text, preg_replace('/[^\w. -]/u', '_', $name) ?? 'export.'.$format, $format === 'csv' ? 'text/csv' : 'text/tab-separated-values');
        }

        return $output;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function columns(array $rows, array $config): array
    {
        $wanted = is_array($config['columns'] ?? null) ? array_values(array_filter(array_map(fn (mixed $column): string => trim((string) $column), $config['columns']))) : [];

        if ($wanted !== []) {
            return $wanted;
        }

        $columns = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $columns[(string) $key] = true;
            }
        }

        return array_keys($columns);
    }

    private function cell(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    private function delimited(array $rows, array $columns, string $delimiter, bool $protect): string
    {
        $stream = fopen('php://temp', 'r+');
        $safe = fn (mixed $value): string => $protect && preg_match('/^[=+\-@\t\r]/', $this->cell($value)) === 1 && ! is_numeric($this->cell($value)) ? "'".$this->cell($value) : $this->cell($value);

        fputcsv($stream, $columns, $delimiter, '"', '');

        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (string $column): string => $safe($row[$column] ?? null), $columns), $delimiter, '"', '');
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    private function markdown(array $rows, array $columns): string
    {
        if ($columns === []) {
            return '';
        }

        $line = fn (array $cells): string => '| '.implode(' | ', array_map(fn (string $cell): string => str_replace(['|', "\n", "\r"], ['\|', ' ', ''], $cell), $cells)).' |';

        return implode("\n", [
            $line($columns),
            '|'.str_repeat(' --- |', count($columns)),
            ...array_map(fn (array $row): string => $line(array_map(fn (string $column): string => $this->cell($row[$column] ?? null), $columns)), $rows),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    private function html(array $rows, array $columns): string
    {
        $escape = fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $head = implode('', array_map(fn (string $column): string => '<th>'.$escape($column).'</th>', $columns));
        $body = implode('', array_map(
            fn (array $row): string => '<tr>'.implode('', array_map(fn (string $column): string => '<td>'.$escape($this->cell($row[$column] ?? null)).'</td>', $columns)).'</tr>',
            $rows,
        ));

        return "<table><thead><tr>{$head}</tr></thead><tbody>{$body}</tbody></table>";
    }
}
