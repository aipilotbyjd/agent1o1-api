<?php

namespace App\Engine\Trigger\Normalizers;

use App\Contracts\PayloadNormalizer;

class AirtableNormalizer implements PayloadNormalizer
{
    public function provider(): string
    {
        return 'airtable';
    }

    public function normalize(array $raw, string $providerEvent): array
    {
        $changedTablesById = $raw['changedTablesById'] ?? [];
        $createdTablesById = $raw['createdTablesById'] ?? [];
        $tableData = array_merge(array_values($changedTablesById), array_values($createdTablesById));

        $records = [];
        foreach ($tableData as $table) {
            foreach ($table['changedFieldsById'] ?? [] as $fieldId => $change) {
                $records[] = ['field_id' => $fieldId, 'change' => $change];
            }
            foreach ($table['createdRecordsById'] ?? [] as $id => $record) {
                $records[] = ['record_id' => $id, 'fields' => $record['cellValuesByFieldId'] ?? []];
            }
        }

        return [
            'trigger' => ['provider' => 'airtable', 'event' => $providerEvent],
            'data' => [
                'webhook_id' => $raw['webhookId'] ?? null,
                'timestamp' => $raw['timestamp'] ?? null,
                'cursor' => $raw['cursor'] ?? null,
                'records' => $records,
                'raw' => $raw,
            ],
            'meta' => [
                'received_at' => now()->toIso8601String(),
                'delivery_id' => $raw['webhookId'] ?? null,
            ],
        ];
    }

    public function extractDedupKey(array $raw, array $headers = []): ?string
    {
        return isset($raw['webhookId'], $raw['cursor'])
            ? $raw['webhookId'].':'.$raw['cursor']
            : null;
    }
}
