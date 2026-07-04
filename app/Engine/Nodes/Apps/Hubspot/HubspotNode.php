<?php

namespace App\Engine\Nodes\Apps\Hubspot;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class HubspotNode extends AppNode
{
    private const BASE_URL = 'https://api.hubapi.com';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'create_contact' => $this->createObject($input, 'contacts'),
            'update_contact' => $this->updateObject($input, 'contacts'),
            'get_contact' => $this->getObject($input, 'contacts'),
            'list_contacts' => $this->listObjects($input, 'contacts'),
            'search_contacts' => $this->searchObjects($input, 'contacts'),
            'create_deal' => $this->createObject($input, 'deals'),
            'update_deal' => $this->updateObject($input, 'deals'),
            'list_deals' => $this->listObjects($input, 'deals'),
            'create_company' => $this->createObject($input, 'companies'),
            'list_companies' => $this->listObjects($input, 'companies'),
            default => $this->fail("Hubspot: unknown operation '{$operation}'"),
        };
    }

    private function listObjects(NodeInput $input, string $type): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/crm/v3/objects/{$type}", array_filter([
                'limit' => $input->config['limit'] ?? 10,
                'after' => $input->config['after'] ?? null,
                'properties' => isset($input->config['properties']) ? implode(',', (array) $input->config['properties']) : null,
            ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Hubspot list_{$type} failed: {$response->body()}");
    }

    private function createObject(NodeInput $input, string $type): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/crm/v3/objects/{$type}", [
                'properties' => $input->config['properties'] ?? [],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Hubspot create_{$type} failed: {$response->body()}");
    }

    private function updateObject(NodeInput $input, string $type): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->patch("/crm/v3/objects/{$type}/{$input->config['object_id']}", [
                'properties' => $input->config['properties'] ?? [],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Hubspot update_{$type} failed: {$response->body()}");
    }

    private function getObject(NodeInput $input, string $type): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/crm/v3/objects/{$type}/{$input->config['object_id']}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Hubspot get_{$type} failed: {$response->body()}");
    }

    private function searchObjects(NodeInput $input, string $type): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/crm/v3/objects/{$type}/search", [
                'query' => $input->config['query'] ?? '',
                'limit' => $input->config['limit'] ?? 10,
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Hubspot search_{$type} failed: {$response->body()}");
    }
}
