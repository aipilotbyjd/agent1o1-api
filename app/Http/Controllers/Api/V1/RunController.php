<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RunResource;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unified, read-only view over all runs in a workspace — workflow executions
 * and agent runs together — backed by the polymorphic Run model. The per-type
 * ExecutionController and AgentRunController remain for type-specific actions
 * (trigger, retry, cancel, steps).
 */
class RunController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionView)) {
            return $forbidden;
        }

        $runs = $workspace->runs()
            ->when($request->query('runnable_type'), fn ($q, $type) => $q->where('runnable_type', $type))
            ->when($request->query('runnable_id'), fn ($q, $id) => $q->where('runnable_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('from'), fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return $this->paginatedResponse('Runs retrieved.', RunResource::collection($runs));
    }

    public function show(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionView)) {
            return $forbidden;
        }

        abort_unless($run->workspace_id === $workspace->id, 404);

        return $this->successResponse('Run retrieved.', new RunResource($run));
    }
}
