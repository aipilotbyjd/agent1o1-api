<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Agents\AgentResource;
use App\Http\Resources\Api\Internal\V1\Agents\AgentVersionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentVersion;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\AgentVersioner;

/**
 * An agent's behavioral history. Versions are written by `AgentObserver`
 * rather than created here: there is no endpoint to mint one by hand,
 * because a version that doesn't correspond to a real edit would make the
 * history lie.
 *
 * Versions are addressed by their per-agent `version` number, not their row
 * id: that is what the UI shows and what a snapshot is remembered by.
 */
class AgentVersionController extends Controller
{
    public function __construct(private readonly AgentVersioner $versioner) {}

    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success([
            'versions' => AgentVersionResource::collection(
                $agent->versions()->orderByDesc('version')->get(),
            ),
        ]);
    }

    public function show(Workspace $workspace, Agent $agent, int $version)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success([
            'version' => AgentVersionResource::make($this->versionOrFail($agent, $version)),
        ]);
    }

    /**
     * Restoring rolls the agent's behavior back *forward*: it produces a new
     * version rather than deleting the ones in between: see
     * `AgentVersioner::restore()`.
     */
    public function restore(Workspace $workspace, Agent $agent, int $version)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $restored = $this->versioner->restore($agent, $this->versionOrFail($agent, $version));

        return ApiResponse::success([
            'agent' => AgentResource::make($agent->fresh()),
            'version' => AgentVersionResource::make($restored),
        ], 'Agent restored.');
    }

    private function versionOrFail(Agent $agent, int $version): AgentVersion
    {
        $found = $agent->versions()->where('version', $version)->first();

        abort_if($found === null, 404);

        return $found;
    }
}
