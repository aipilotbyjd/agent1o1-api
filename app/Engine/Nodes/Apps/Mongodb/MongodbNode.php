<?php

namespace App\Engine\Nodes\Apps\Mongodb;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class MongodbNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        // MongoDB Data API (HTTPS) — avoids requiring the mongodb PHP extension
        $baseUrl = $input->credentials['data_api_url'] ?? '';
        $apiKey = $input->credentials['api_key'] ?? '';

        if (empty($baseUrl)) {
            return $this->fail('MongoDB: data_api_url credential is required');
        }

        $action = match ($operation) {
            'find' => 'find',
            'find_one' => 'findOne',
            'insert_one' => 'insertOne',
            'insert_many' => 'insertMany',
            'update_one' => 'updateOne',
            'delete_one' => 'deleteOne',
            default => null,
        };

        if ($action === null) {
            return $this->fail("Mongodb: unknown operation '{$operation}'");
        }

        $payload = array_filter([
            'dataSource' => $input->credentials['data_source'] ?? 'Cluster0',
            'database' => $input->config['database'] ?? ($input->credentials['database'] ?? ''),
            'collection' => $input->config['collection'],
            'filter' => $input->config['filter'] ?? null,
            'document' => $input->config['document'] ?? null,
            'documents' => $input->config['documents'] ?? null,
            'update' => $input->config['update'] ?? null,
            'limit' => $input->config['limit'] ?? null,
        ]);

        $response = $this->http()
            ->withHeaders(['api-key' => $apiKey])
            ->post("{$baseUrl}/action/{$action}", $payload);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Mongodb {$operation} failed: {$response->body()}");
    }
}
