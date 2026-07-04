<?php

namespace App\Engine\Nodes\Apps\Notion;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;

class NotionNode extends AppNode
{
    private const BASE_URL = 'https://api.notion.com/v1';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'query_database' => $this->queryDatabase($input),
            'create_page' => $this->createPage($input),
            'update_page' => $this->updatePage($input),
            'get_page' => $this->getPage($input),
            'append_block', 'append_blocks' => $this->appendBlocks($input),
            'list_databases' => $this->listDatabases($input),
            default => $this->fail("Notion: unknown operation '{$operation}'"),
        };
    }

    private function notionHttp(NodeInput $input): PendingRequest
    {
        return $this->httpWithAuth($input, self::BASE_URL)
            ->withHeaders(['Notion-Version' => '2022-06-28']);
    }

    private function queryDatabase(NodeInput $input): NodeResult
    {
        $response = $this->notionHttp($input)
            ->post("/databases/{$input->config['database_id']}/query", array_filter([
                'filter' => $input->config['filter'] ?? null,
                'sorts' => $input->config['sorts'] ?? null,
                'page_size' => $input->config['page_size'] ?? 100,
            ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Notion query_database failed: {$response->body()}");
    }

    private function createPage(NodeInput $input): NodeResult
    {
        $response = $this->notionHttp($input)->post('/pages', [
            'parent' => $input->config['parent'] ?? ['database_id' => $input->config['database_id'] ?? ''],
            'properties' => $input->config['properties'] ?? [],
            'children' => $input->config['children'] ?? [],
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Notion create_page failed: {$response->body()}");
    }

    private function updatePage(NodeInput $input): NodeResult
    {
        $response = $this->notionHttp($input)
            ->patch("/pages/{$input->config['page_id']}", [
                'properties' => $input->config['properties'] ?? [],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Notion update_page failed: {$response->body()}");
    }

    private function getPage(NodeInput $input): NodeResult
    {
        $response = $this->notionHttp($input)->get("/pages/{$input->config['page_id']}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Notion get_page failed: {$response->body()}");
    }

    private function listDatabases(NodeInput $input): NodeResult
    {
        $response = $this->notionHttp($input)->post('/search', array_filter([
            'filter' => ['value' => 'database', 'property' => 'object'],
            'page_size' => $input->config['page_size'] ?? 100,
            'query' => $input->config['query'] ?? null,
        ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Notion list_databases failed: {$response->body()}");
    }

    private function appendBlocks(NodeInput $input): NodeResult
    {
        $response = $this->notionHttp($input)
            ->patch("/blocks/{$input->config['block_id']}/children", [
                'children' => $input->config['children'] ?? [],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Notion append_blocks failed: {$response->body()}");
    }
}
