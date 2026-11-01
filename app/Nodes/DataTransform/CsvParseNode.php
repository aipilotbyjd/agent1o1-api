<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Nodes\Support\FilePayload;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

/**
 * Turns a CSV, TSV or spreadsheet (xlsx, xls, ods) into rows a workflow can
 * loop over, filter and send on: one object per row, keyed by the header.
 */
class CsvParseNode extends PureDataNode
{
    private const int MAX_ROWS = 20000;

    private const int MAX_COLUMNS = 200;

    private const array SPREADSHEET_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/zip',
    ];

    public function type(): string
    {
        return 'csv_parse';
    }

    public function name(): string
    {
        return 'Spreadsheet Parse';
    }

    public function icon(): string
    {
        return 'csv-01';
    }

    public function description(): string
    {
        return 'Turns CSV text or a CSV, Excel or OpenDocument file into rows, one object per row keyed by the column headings.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'text' => Field::textarea('CSV text', 'Paste CSV or use {{templates}}. Leave empty to read a file instead.'),
                ...FilePayload::inputFields('Spreadsheet'),
                'delimiter' => Field::select('Separator', ['auto' => 'Detect', ',' => 'Comma', ';' => 'Semicolon', "\t" => 'Tab', '|' => 'Pipe'], 'CSV only.', 'auto'),
                'has_header' => Field::boolean('First row is the headings', 'Off gives columns named column_1, column_2 and so on.', true),
                'sheet' => Field::advanced(Field::text('Sheet', 'Excel or OpenDocument only: the sheet name. Leave empty for the first.')),
                'skip_empty' => Field::advanced(Field::boolean('Skip empty rows', null, true)),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'rows' => ['title' => 'Rows', 'description' => 'One object per row, keyed by the headings.'],
                'headers' => ['title' => 'Headings', 'description' => 'The column names, in order.'],
                'row_count' => ['title' => 'Row count', 'description' => 'How many rows were read.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $table = filled($config['text'] ?? null) ? $this->csv((string) $config['text'], (string) ($config['delimiter'] ?? 'auto')) : $this->fromFile($config);

        if ($table === []) {
            return ['rows' => [], 'headers' => [], 'row_count' => 0];
        }

        if ((bool) ($config['skip_empty'] ?? true)) {
            $table = array_values(array_filter($table, fn (array $row): bool => count(array_filter($row, fn (mixed $cell): bool => trim((string) $cell) !== '')) > 0));
        }

        $width = min(self::MAX_COLUMNS, max(array_map('count', $table ?: [[]])));
        $hasHeader = (bool) ($config['has_header'] ?? true);
        $headers = $hasHeader ? $this->headers(array_pad(array_slice(array_shift($table) ?? [], 0, $width), $width, '')) : array_map(fn (int $i): string => 'column_'.($i + 1), range(0, max(0, $width - 1)));

        if (count($table) > self::MAX_ROWS) {
            throw new InvalidArgumentException('The file has more than '.number_format(self::MAX_ROWS).' rows. Split it up.');
        }

        $rows = array_map(fn (array $row): array => array_combine($headers, array_map(fn (mixed $cell): mixed => is_string($cell) ? trim($cell) : $cell, array_pad(array_slice($row, 0, $width), $width, ''))), $table);

        return ['rows' => $rows, 'headers' => $headers, 'row_count' => count($rows)];
    }

    /**
     * @return list<list<mixed>>
     */
    private function csv(string $text, string $delimiter): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;

        if (strlen($text) > FilePayload::MAX_BYTES) {
            throw new InvalidArgumentException('The CSV is too large to read.');
        }

        if ($delimiter === 'auto' || $delimiter === '') {
            $delimiter = $this->detect($text);
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);

        $rows = [];

        while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $rows[] = $row;

            if (count($rows) > self::MAX_ROWS + 1) {
                break;
            }
        }

        fclose($stream);

        return $rows;
    }

    private function detect(string $text): string
    {
        $sample = implode("\n", array_slice(preg_split('/\R/', $text) ?: [], 0, 5));
        $best = ',';
        $most = 0;

        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = substr_count($sample, $candidate);

            if ($count > $most) {
                $best = $candidate;
                $most = $count;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<list<mixed>>
     */
    private function fromFile(array $config): array
    {
        $file = FilePayload::read($config);

        if (! in_array($file['mime_type'], self::SPREADSHEET_TYPES, true)) {
            return $this->csv($file['content'], (string) ($config['delimiter'] ?? 'auto'));
        }

        $path = tempnam(sys_get_temp_dir(), 'sheet');
        file_put_contents($path, $file['content']);

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = filled($config['sheet'] ?? null) ? $spreadsheet->getSheetByName(trim((string) $config['sheet'])) : $spreadsheet->getSheet(0);

            if ($sheet === null) {
                throw new InvalidArgumentException('There is no sheet called “'.trim((string) $config['sheet']).'” in that file.');
            }

            return array_map('array_values', $sheet->toArray(null, true, false, false));
        } catch (ReaderException $exception) {
            throw new InvalidArgumentException('That file could not be read as a spreadsheet: '.$exception->getMessage());
        } finally {
            @unlink($path);
        }
    }

    /**
     * Headings made usable as keys: trimmed, blanks named, repeats numbered.
     *
     * @param  list<mixed>  $cells
     * @return list<string>
     */
    private function headers(array $cells): array
    {
        $seen = [];

        return array_map(function (mixed $cell, int $index) use (&$seen): string {
            $name = trim((string) $cell) ?: 'column_'.($index + 1);
            $seen[$name] = ($seen[$name] ?? 0) + 1;

            return $seen[$name] > 1 ? "{$name}_{$seen[$name]}" : $name;
        }, $cells, array_keys($cells));
    }
}
