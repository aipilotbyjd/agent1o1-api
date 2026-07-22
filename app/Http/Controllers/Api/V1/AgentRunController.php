<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AgentRunResource;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only run history & step traces for an agent — every conversation reply,
 * trigger fire, and manual run is recorded as an AgentRun.
 */
class AgentRunController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $runs = $agent->runs()
            ->withCount('steps')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('source'), fn ($q, $source) => $q->where('source', $source))
            ->latest()
            ->paginate((int) $request->query('per_page', 25));

        return $this->paginatedResponse('Agent runs retrieved.', AgentRunResource::collection($runs));
    }

    public function show(Request $request, Workspace $workspace, Agent $agent, AgentRun $run): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        if ($run->agent_id !== $agent->id) {
            return $this->errorResponse('Agent run not found.', 404);
        }

        return $this->successResponse(
            'Agent run retrieved.',
            new AgentRunResource($run->load(['steps', 'internalRuns'])->loadCount('steps')),
        );
    }
}
