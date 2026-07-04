<?php

namespace App\Engine\Nodes\Apps\Rag;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class VectorStoreWriterNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $provider = $input->config['provider'] ?? ($input->credentials['provider'] ?? 'pinecone');

        return match ($provider) {
            'pinecone' => $this->writePinecone($input),
            default => $this->fail("VectorStoreWriter: unknown provider '{$provider}'"),
        };
    }

    private function writePinecone(NodeInput $input): NodeResult
    {
        $host = $input->credentials['index_host'] ?? '';
        $apiKey = $input->credentials['api_key'] ?? '';
        $vectors = $input->config['vectors'] ?? [];

        if (empty($host) || empty($vectors)) {
            return $this->fail('VectorStoreWriter: index_host credential and vectors are required');
        }

        $response = $this->http()
            ->withHeaders(['Api-Key' => $apiKey])
            ->post("https://{$host}/vectors/upsert", [
                'vectors' => $vectors,
                'namespace' => $input->config['namespace'] ?? '',
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Pinecone upsert failed: {$response->body()}");
    }
}
