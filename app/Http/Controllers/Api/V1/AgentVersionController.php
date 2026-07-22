<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AgentResource;
use App\Http\Resources\V1\AgentVersionResource;
use App\Models\Agent;
use App\Models\Workspace;
use App\Services\Agent\AgentVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Versioning & rollback (roadmap item 10): browse an agent's config history,
 * diff any version against the live config, and roll back to a snapshot.
 */
class AgentVersionController extends Controller
{
    public function __construct(private readonly AgentVersionService $versions) {}

    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $versions = $agent->versions()->with('creator')->paginate((int) $request->query('per_page', 25));

        return $this->paginatedResponse('Agent versions retrieved.', AgentVersionResource::collection($versions));
    }

    public function show(Request $request, Workspace $workspace, Agent $agent, string $version): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $record = $agent->versions()->with('creator')->where('version', $version)->firstOrFail();

        return $this->successResponse('Agent version retrieved.', new AgentVersionResource($record));
    }

    /**
     * Field-level diff between a stored version and the agent's current config.
     */
    public function diff(Request $request, Workspace $workspace, Agent $agent, string $version): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $record = $agent->versions()->where('version', $version)->firstOrFail();
        $diff = $this->versions->diff($record->snapshot, $this->versions->snapshot($agent));

        return $this->successResponse('Agent version diff retrieved.', [
            'version' => (int) $version,
            'changes' => $diff,
        ]);
    }

    public function rollback(Request $request, Workspace $workspace, Agent $agent, string $version): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $record = $agent->versions()->where('version', $version)->firstOrFail();
        $restored = $this->versions->rollback($agent, $record, $request->user());

        return $this->successResponse(
            "Agent rolled back to version {$version}.",
            new AgentResource($restored->load(['toolConfigs', 'skills'])),
        );
    }
}
