<?php

namespace App\Services\Triggers\Push;

/**
 * "New row" and "row added or updated" in a Google Sheet, the moment the
 * spreadsheet changes instead of by reading it every minute.
 */
class GoogleSheetsSource extends GoogleFileSource
{
    public function provider(): string
    {
        return 'google_sheets';
    }

    protected function nodeTypes(): array
    {
        return ['google_sheets_get_values', 'google_sheets_list_updated_rows', 'google_sheets_list_deleted_rows'];
    }

    protected function fileIdField(): string
    {
        return 'spreadsheet_id';
    }
}
