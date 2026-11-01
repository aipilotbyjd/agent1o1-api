<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Agents\ReflectionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\Reflection;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\ReflectionApplier;
use Illuminate\Http\Request;

class ReflectionController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success([
            'reflections' => ReflectionResource::collection(
                $agent->reflections()
                    ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->toString()))
                    ->latest()
                    ->get(),
            ),
        ]);
    }

    public function show(Workspace $workspace, Agent $agent, Reflection $reflection)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($reflection->agent_id !== $agent->id, 404);

        return ApiResponse::success(['reflection' => ReflectionResource::make($reflection)]);
    }

    public function apply(Request $request, Workspace $workspace, Agent $agent, Reflection $reflection, ReflectionApplier $applier)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($reflection->agent_id !== $agent->id, 404);

        $reflection = $applier->apply($reflection, $request->user());

        return ApiResponse::success(['reflection' => ReflectionResource::make($reflection)], 'Reflection applied successfully.');
    }

    public function dismiss(Workspace $workspace, Agent $agent, Reflection $reflection, ReflectionApplier $applier)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($reflection->agent_id !== $agent->id, 404);

        $reflection = $applier->dismiss($reflection);

        return ApiResponse::success(['reflection' => ReflectionResource::make($reflection)], 'Reflection dismissed.');
    }
}
