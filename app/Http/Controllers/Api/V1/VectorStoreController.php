<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\VectorStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VectorStoreController extends Controller
{
    public function __construct(private readonly VectorStoreService $vectorStore) {}

    public function ingest(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $denied;
        }

        $data = $request->validate([
            'collection' => ['nullable', 'string', 'max:100'],
            'text' => ['required', 'string'],
            'source' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $count = $this->vectorStore->ingest(
            $workspace,
            $data['collection'] ?? 'default',
            $data['text'],
            $data['source'] ?? null,
            $data['metadata'] ?? [],
        );

        return $this->successResponse('Document ingested.', ['chunks_stored' => $count], 201);
    }

    public function query(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowView)) {
            return $denied;
        }

        $data = $request->validate([
            'collection' => ['nullable', 'string', 'max:100'],
            'query' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $results = $this->vectorStore->query(
            $workspace,
            $data['collection'] ?? 'default',
            $data['query'],
            $data['limit'] ?? 5,
        );

        return $this->successResponse('Query complete.', $results);
    }
}
