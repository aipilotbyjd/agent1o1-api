<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\Concerns\ListsDriveFiles;

/**
 * Spreadsheets (via Drive), and one spreadsheet's tabs.
 */
class GoogleSheetsOptions extends HttpOptionsSource
{
    use ListsDriveFiles;

    private const string SPREADSHEETS_URL = 'https://sheets.googleapis.com/v4/spreadsheets';

    private const string SPREADSHEET_MIME_TYPE = 'application/vnd.google-apps.spreadsheet';

    public function sources(): array
    {
        return ['google_sheets.spreadsheets', 'google_sheets.sheets'];
    }

    protected function appName(): string
    {
        return 'Google Sheets';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return $source === 'google_sheets.sheets'
            ? $this->sheets($query)
            : $this->driveFiles($query, self::SPREADSHEET_MIME_TYPE);
    }

    /**
     * The chosen spreadsheet's tabs, in tab order. Each value is the tab name
     * as an A1 range covering the whole sheet, so picking one is a complete
     * `range` on its own.
     */
    private function sheets(NodeOptionsQuery $query): NodeOptionsPage
    {
        $body = $this->getJson($query, self::SPREADSHEETS_URL.'/'.rawurlencode((string) $query->configString('spreadsheet_id')), [
            'fields' => 'sheets.properties(sheetId,title,index,gridProperties(rowCount,columnCount))',
        ]);

        $options = collect($body['sheets'] ?? [])
            ->pluck('properties')
            ->filter(fn (mixed $sheet): bool => is_array($sheet) && isset($sheet['title']) && $query->matches((string) $sheet['title']))
            ->sortBy(fn (array $sheet): int => (int) ($sheet['index'] ?? 0))
            ->map(fn (array $sheet): NodeOption => new NodeOption(
                self::a1SheetName((string) $sheet['title']),
                (string) $sheet['title'],
                isset($sheet['gridProperties']['rowCount'], $sheet['gridProperties']['columnCount'])
                    ? "{$sheet['gridProperties']['rowCount']} rows × {$sheet['gridProperties']['columnCount']} columns"
                    : null,
            ));

        return new NodeOptionsPage($options);
    }

    /**
     * A tab name as Sheets reads it in a range: bare when it's a plain word,
     * otherwise quoted with any `'` doubled, including names that would
     * read as a cell reference (`'AB12'`, `'R1C1'`).
     */
    public static function a1SheetName(string $title): string
    {
        $isPlainWord = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $title) === 1;
        $looksLikeCell = preg_match('/^([A-Za-z]{1,3}\d+|R\d*C\d*)$/i', $title) === 1;

        return $isPlainWord && ! $looksLikeCell ? $title : "'".str_replace("'", "''", $title)."'";
    }
}
