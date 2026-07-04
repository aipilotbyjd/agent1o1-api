<?php

namespace App\Engine\Nodes\Apps\Airtable;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class AirtableNode extends AppNode
{
    private const BASE_URL = 'https://api.airtable.com/v0';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'list_records' => $this->listRecords($input),
            'get_record' => $this->getRecord($input),
            'create_record' => $this->createRecord($input),
            'update_record' => $this->updateRecord($input),
            'delete_record' => $this->deleteRecord($input),
            default => $this->fail("Airtable: unknown operation '{$operation}'"),
        };
    }

    private function tablePath(NodeInput $input): string
    {
        return "/{$input->config['base_id']}/{$input->config['table']}";
    }

    private function listRecords(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get($this->tablePath($input), array_filter([
                'maxRecords' => $input->config['max_records'] ?? 100,
                'filterByFormula' => $input->config['filter_formula'] ?? null,
                'view' => $input->config['view'] ?? null,
            ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Airtable list_records failed: {$response->body()}");
    }

    private function getRecord(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get($this->tablePath($input)."/{$input->config['record_id']}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Airtable get_record failed: {$response->body()}");
    }

    private function createRecord(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post($this->tablePath($input), ['fields' => $input->config['fields'] ?? []]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Airtable create_record failed: {$response->body()}");
    }

    private function updateRecord(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->patch($this->tablePath($input)."/{$input->config['record_id']}", [
                'fields' => $input->config['fields'] ?? [],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Airtable update_record failed: {$response->body()}");
    }

    private function deleteRecord(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->delete($this->tablePath($input)."/{$input->config['record_id']}");

        return $response->successful()
            ? $this->success(['deleted' => true])
            : $this->fail("Airtable delete_record failed: {$response->body()}");
    }
}
