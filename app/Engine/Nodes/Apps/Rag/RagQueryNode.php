<?php

namespace App\Engine\Nodes\Apps\Rag;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class RagQueryNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $provider = $input->config['provider'] ?? ($input->credentials['provider'] ?? 'pinecone');

        return match ($provider) {
            'pinecone' => $this->queryPinecone($input),
            default => $this->fail("RagQuery: unknown provider '{$provider}'"),
        };
    }

    private function queryPinecone(NodeInput $input): NodeResult
    {
        $host = $input->credentials['index_host'] ?? '';
        $apiKey = $input->credentials['api_key'] ?? '';
        $vector = $input->config['vector'] ?? [];

        if (empty($host) || empty($vector)) {
            return $this->fail('RagQuery: index_host credential and query vector are required');
        }

        $response = $this->http()
            ->withHeaders(['Api-Key' => $apiKey])
            ->post("https://{$host}/query", array_filter([
                'vector' => $vector,
                'topK' => (int) ($input->config['top_k'] ?? 5),
                'namespace' => $input->config['namespace'] ?? null,
                'includeMetadata' => true,
                'filter' => $input->config['filter'] ?? null,
            ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Pinecone query failed: {$response->body()}");
    }
}
