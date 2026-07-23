<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ExecutionNode;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\NodeOutputSchemaService;
use Illuminate\Http\JsonResponse;

class NodeOutputSchemaController extends Controller
{
    private const SAMPLE_LIMIT = 10;

    public function __construct(private readonly NodeOutputSchemaService $schemaService) {}

    public function show(Workspace $workspace, Workflow $workflow, string $nodeId): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionView)) {
            return $denied;
        }

        $outputs = ExecutionNode::query()
            ->whereHas('execution', function ($q) use ($workflow) {
                $q->where('runnable_id', $workflow->id)
                    ->where('status', 'completed');
            })
            ->where('node_id', $nodeId)
            ->where('status', 'completed')
            ->whereNotNull('output_data')
            ->orderByDesc('id')
            ->limit(self::SAMPLE_LIMIT)
            ->pluck('output_data')
            ->filter()
            ->values()
            ->toArray();

        if (empty($outputs)) {
            return $this->successResponse('No execution data found for this node.', [
                'node_id' => $nodeId,
                'sample_count' => 0,
                'schema' => [],
            ]);
        }

        return $this->successResponse('Schema inferred successfully.', [
            'node_id' => $nodeId,
            'sample_count' => count($outputs),
            'schema' => $this->schemaService->infer($outputs),
        ]);
    }
}
