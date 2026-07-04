<?php

namespace App\Engine\Nodes\Apps\Salesforce;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;

class SalesforceNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'create_record' => $this->createRecord($input),
            'update_record' => $this->updateRecord($input),
            'get_record' => $this->getRecord($input),
            'query' => $this->query($input),
            'delete_record' => $this->deleteRecord($input),
            default => $this->fail("Salesforce: unknown operation '{$operation}'"),
        };
    }

    private function sfHttp(NodeInput $input): PendingRequest
    {
        $instanceUrl = $input->credentials['instance_url'] ?? '';

        return $this->httpWithAuth($input)
            ->baseUrl("{$instanceUrl}/services/data/v59.0");
    }

    private function createRecord(NodeInput $input): NodeResult
    {
        $response = $this->sfHttp($input)
            ->post("/sobjects/{$input->config['object']}", $input->config['fields'] ?? []);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Salesforce create_record failed: {$response->body()}");
    }

    private function updateRecord(NodeInput $input): NodeResult
    {
        $response = $this->sfHttp($input)
            ->patch("/sobjects/{$input->config['object']}/{$input->config['record_id']}", $input->config['fields'] ?? []);

        return $response->successful()
            ? $this->success(['updated' => true])
            : $this->fail("Salesforce update_record failed: {$response->body()}");
    }

    private function getRecord(NodeInput $input): NodeResult
    {
        $response = $this->sfHttp($input)
            ->get("/sobjects/{$input->config['object']}/{$input->config['record_id']}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Salesforce get_record failed: {$response->body()}");
    }

    private function query(NodeInput $input): NodeResult
    {
        $response = $this->sfHttp($input)->get('/query', ['q' => $input->config['soql'] ?? '']);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Salesforce query failed: {$response->body()}");
    }

    private function deleteRecord(NodeInput $input): NodeResult
    {
        $response = $this->sfHttp($input)
            ->delete("/sobjects/{$input->config['object']}/{$input->config['record_id']}");

        return $response->successful()
            ? $this->success(['deleted' => true])
            : $this->fail("Salesforce delete_record failed: {$response->body()}");
    }
}
