<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\SyncAgentTagsRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\StoreTagRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\SyncWorkflowTagsRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\UpdateTagRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentResource;
use App\Http\Resources\Api\Internal\V1\Workflows\TagResource;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Workflows\Tag;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;

class TagController extends Controller
{
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowView);

        $tags = $workspace->tags()
            ->withCount(['workflows', 'agents'])
            ->orderBy('name')
            ->get();

        return ApiResponse::success(['tags' => TagResource::collection($tags)]);
    }

    public function store(StoreTagRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowManage);

        if ($workspace->tags()->where('name', $request->validated('name'))->exists()) {
            return ApiResponse::error('A tag with this name already exists.', 422);
        }

        $tag = $workspace->tags()->create($request->validated());

        return ApiResponse::created(['tag' => TagResource::make($tag)], 'Tag created successfully.');
    }

    public function update(UpdateTagRequest $request, Workspace $workspace, Tag $tag)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $tag);

        $tag->update($request->validated());

        return ApiResponse::success(['tag' => TagResource::make($tag)], 'Tag updated successfully.');
    }

    public function destroy(Workspace $workspace, Tag $tag)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $tag);

        $tag->delete();

        return ApiResponse::noContent();
    }

    public function syncForWorkflow(SyncWorkflowTagsRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $workflow->tags()->sync($request->validated('tag_ids'));

        return ApiResponse::success(['workflow' => WorkflowResource::make($workflow->load('tags'))], 'Workflow tags updated.');
    }

    public function syncForAgent(SyncAgentTagsRequest $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $agent->tags()->sync($request->validated('tag_ids'));

        return ApiResponse::success(['agent' => AgentResource::make($agent->load('tags'))], 'Agent tags updated.');
    }
}
