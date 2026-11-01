<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Actions\Agents\DuplicateAgentAction;
use App\Enums\Billing\PlanLimit;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\DuplicateAgentRequest;
use App\Http\Requests\Api\Internal\V1\Agents\StoreAgentRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateAgentRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\WorkspaceModels;
use App\Services\Billing\PlanLimitGate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        $agents = $workspace->agents()
            ->with('tags')
            ->withCount('sessions')
            ->withMax('sessions', 'last_activity_at')
            ->when($request->query('tag_id'), fn ($query, $tagId) => Str::isUuid($tagId)
                ? $query->whereHas('tags', fn ($tags) => $tags->whereKey($tagId))
                : $query->whereRaw('1 = 0'))
            ->latest()
            ->get();

        return ApiResponse::success([
            'agents' => AgentResource::collection($agents),
        ]);
    }

    /**
     * An agent created without a model starts on the workspace's default
     * model, when one is set: see `WorkspaceModels`.
     */
    public function store(StoreAgentRequest $request, Workspace $workspace, PlanLimitGate $limits, WorkspaceModels $models)
    {
        $this->requirePermission(Permission::AgentManage);
        $limits->assertCanCreate($workspace, PlanLimit::Agents);

        $agent = $workspace->agents()->create([
            ...$request->validated(),
            'model_catalog_id' => $request->validated('model_catalog_id') ?? ($request->filled('model') ? null : $models->default($workspace->id)?->id),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')).'-'.Str::random(6),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['agent' => AgentResource::make($agent)], 'Agent created successfully.');
    }

    public function show(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success(['agent' => AgentResource::make($agent->load('tags'))]);
    }

    public function update(UpdateAgentRequest $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $agent->update($request->validated());

        return ApiResponse::success(['agent' => AgentResource::make($agent)], 'Agent updated successfully.');
    }

    /**
     * Configuration only: see `DuplicateAgentAction` for what a copy does
     * and doesn't carry over.
     */
    public function duplicate(DuplicateAgentRequest $request, Workspace $workspace, Agent $agent, DuplicateAgentAction $duplicate)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $copy = $duplicate->execute($agent, $request->user(), $request->validated('name'));

        return ApiResponse::created(['agent' => AgentResource::make($copy)], 'Agent duplicated successfully.');
    }

    public function destroy(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $agent->delete();

        return ApiResponse::noContent();
    }

    /**
     * `destroy` only soft-deletes, so the trash is every trashed agent:
     * most recently trashed first.
     */
    public function trash(Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        $agents = $workspace->agents()
            ->onlyTrashed()
            ->with('tags')
            ->latest('deleted_at')
            ->get();

        return ApiResponse::success([
            'agents' => AgentResource::collection($agents),
        ]);
    }

    /**
     * A trashed agent stops counting toward `PlanLimit::Agents`, so bringing
     * one back has to fit under the cap like creating one would.
     */
    public function restore(Workspace $workspace, Agent $agent, PlanLimitGate $limits)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_unless($agent->trashed(), 404);
        $limits->assertCanCreate($workspace, PlanLimit::Agents);

        $agent->restore();

        return ApiResponse::success(['agent' => AgentResource::make($agent->load('tags'))], 'Agent restored successfully.');
    }

    public function forceDestroy(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_unless($agent->trashed(), 404);

        $agent->forceDelete();

        return ApiResponse::noContent();
    }
}
