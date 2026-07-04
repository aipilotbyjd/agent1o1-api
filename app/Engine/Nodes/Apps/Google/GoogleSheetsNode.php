<?php

namespace App\Engine\Nodes\Apps\Google;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class GoogleSheetsNode extends AppNode
{
    public const TYPE = 'google_sheets';

    private const BASE_URL = 'https://sheets.googleapis.com/v4';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'get_rows' => $this->getRows($input),
            'append_row' => $this->appendRow($input),
            'update_row' => $this->updateRow($input),
            'clear_range' => $this->clearRange($input),
            'delete_rows' => $this->deleteRows($input),
            'lookup_rows' => $this->lookupRows($input),
            'create_spreadsheet' => $this->createSpreadsheet($input),
            'get_spreadsheet_info' => $this->getSpreadsheetInfo($input),
            default => $this->fail("GoogleSheets: unknown operation '{$operation}'"),
        };
    }

    private function getRows(NodeInput $input): NodeResult
    {
        $spreadsheetId = $input->config['spreadsheet_id'];
        $range = $input->config['range'] ?? 'Sheet1';
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/spreadsheets/{$spreadsheetId}/values/{$range}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleSheets get_rows failed: {$response->body()}");
    }

    private function appendRow(NodeInput $input): NodeResult
    {
        $spreadsheetId = $input->config['spreadsheet_id'];
        $range = $input->config['range'] ?? 'Sheet1';
        $values = $input->config['values'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/spreadsheets/{$spreadsheetId}/values/{$range}:append?valueInputOption=USER_ENTERED", [
                'values' => is_array($values[0] ?? null) ? $values : [$values],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleSheets append_row failed: {$response->body()}");
    }

    private function updateRow(NodeInput $input): NodeResult
    {
        $spreadsheetId = $input->config['spreadsheet_id'];
        $range = $input->config['range'];
        $values = $input->config['values'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->put("/spreadsheets/{$spreadsheetId}/values/{$range}?valueInputOption=USER_ENTERED", [
                'values' => is_array($values[0] ?? null) ? $values : [$values],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleSheets update_row failed: {$response->body()}");
    }

    private function clearRange(NodeInput $input): NodeResult
    {
        $spreadsheetId = $input->config['spreadsheet_id'];
        $range = $input->config['range'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/spreadsheets/{$spreadsheetId}/values/{$range}:clear");

        return $response->successful()
            ? $this->success(['cleared' => true])
            : $this->fail("GoogleSheets clear_range failed: {$response->body()}");
    }

    private function deleteRows(NodeInput $input): NodeResult
    {
        $spreadsheetId = $input->config['spreadsheet_id'];
        $sheetId = $input->config['sheet_id'] ?? 0;
        $startIndex = $input->config['start_index'];
        $endIndex = $input->config['end_index'];

        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/spreadsheets/{$spreadsheetId}:batchUpdate", [
                'requests' => [[
                    'deleteDimension' => [
                        'range' => [
                            'sheetId' => $sheetId,
                            'dimension' => 'ROWS',
                            'startIndex' => $startIndex,
                            'endIndex' => $endIndex,
                        ],
                    ],
                ]],
            ]);

        return $response->successful()
            ? $this->success(['deleted' => true])
            : $this->fail("GoogleSheets delete_rows failed: {$response->body()}");
    }

    private function lookupRows(NodeInput $input): NodeResult
    {
        $spreadsheetId = $input->config['spreadsheet_id'];
        $range = $input->config['range'] ?? 'Sheet1';
        $lookupColumn = $input->config['lookup_column'] ?? 'A';
        $lookupValue = $input->config['lookup_value'];

        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/spreadsheets/{$spreadsheetId}/values/{$range}");

        if (! $response->successful()) {
            return $this->fail("GoogleSheets lookup_rows failed: {$response->body()}");
        }

        $data = $response->json();
        $rows = $data['values'] ?? [];
        $colIndex = ord(strtoupper($lookupColumn)) - ord('A');

        $matched = array_values(array_filter($rows, fn ($row) => ($row[$colIndex] ?? null) === $lookupValue));

        return $this->success(['rows' => $matched, 'count' => count($matched)]);
    }

    private function createSpreadsheet(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/spreadsheets', [
                'properties' => ['title' => $input->config['title'] ?? 'New Spreadsheet'],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleSheets create_spreadsheet failed: {$response->body()}");
    }

    private function getSpreadsheetInfo(NodeInput $input): NodeResult
    {
        $spreadsheetId = $input->config['spreadsheet_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/spreadsheets/{$spreadsheetId}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleSheets get_spreadsheet_info failed: {$response->body()}");
    }
}
