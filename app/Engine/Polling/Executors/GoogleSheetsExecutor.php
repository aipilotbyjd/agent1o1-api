<?php

namespace App\Engine\Polling\Executors;

use App\Contracts\TriggerExecutor;
use App\Engine\Trigger\TriggerEventDispatcher;
use App\Models\Trigger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Polls a Google Sheet for new rows (keyed by 1-based row number) and dispatches
 * one trigger event per new row, mapping header columns to values.
 */
class GoogleSheetsExecutor implements TriggerExecutor
{
    public function __construct(private readonly TriggerEventDispatcher $dispatcher) {}

    public function execute(Trigger $trigger): void
    {
        $spreadsheetId = $trigger->getFieldValue('spreadsheet_id');
        $range = $trigger->getFieldValue('range') ?: 'Sheet1';

        if (! $spreadsheetId) {
            Log::warning("Google Sheets trigger {$trigger->id} has no spreadsheet_id");

            return;
        }

        $credential = $trigger->credential;

        if (! $credential) {
            Log::warning("Google Sheets trigger {$trigger->id} has no credential");

            return;
        }

        $accessToken = $credential->getDecryptedData()['access_token'] ?? null;

        if (! $accessToken) {
            Log::warning("Google Sheets trigger {$trigger->id} credential missing access_token");

            return;
        }

        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/".urlencode($range);

        try {
            $response = Http::withToken($accessToken)->get($url);

            if (! $response->successful()) {
                Log::warning("Google Sheets trigger {$trigger->id} got HTTP {$response->status()}");

                return;
            }

            $values = $response->json('values') ?? [];

            if (count($values) <= 1) {
                return;
            }

            $headers = array_shift($values);
            $seenIds = $trigger->polling_last_seen_ids ?? [];
            $newSeenIds = $seenIds;

            foreach ($values as $index => $row) {
                $key = (string) ($index + 2); // 1-based row number, skipping header

                if (in_array($key, $seenIds, true)) {
                    continue;
                }

                $record = [];
                foreach ($headers as $i => $header) {
                    $record[$header] = $row[$i] ?? null;
                }
                $record['_row_number'] = (int) $key;

                $newSeenIds[] = $key;
                $this->dispatcher->dispatch($trigger, ['item' => $record, 'item_id' => $key], 'google_sheets', 'poll', $key);
            }

            $trigger->update([
                'polling_last_seen_ids' => array_slice($newSeenIds, -500),
            ]);
        } catch (\Throwable $e) {
            Log::error("Google Sheets trigger {$trigger->id} failed", ['error' => $e->getMessage()]);
        }
    }
}
