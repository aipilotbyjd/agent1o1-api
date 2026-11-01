<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Agents\ReflectionRunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\ReflectionRun;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\ReflectionAnalyzer;

class ReflectionRunController extends Controller
{
    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success([
            'runs' => ReflectionRunResource::collection(
                $agent->reflectionRuns()->withCount('reflections')->latest()->get(),
            ),
        ]);
    }

    /**
     * Runs a reflection pass immediately, outside its configured schedule:
     * the API equivalent of the reference feature's "ask it to review its
     * recent work" escape hatch.
     */
    public function store(Workspace $workspace, Agent $agent, ReflectionAnalyzer $analyzer)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $run = $analyzer->run($agent);

        return ApiResponse::created(['run' => ReflectionRunResource::make($run->load('reflections'))]);
    }

    public function show(Workspace $workspace, Agent $agent, ReflectionRun $run)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($run->agent_id !== $agent->id, 404);

        return ApiResponse::success(['run' => ReflectionRunResource::make($run->load('reflections'))]);
    }
}
