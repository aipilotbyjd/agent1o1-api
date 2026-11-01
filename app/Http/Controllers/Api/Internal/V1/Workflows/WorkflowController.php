<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Billing\PlanLimit;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\StoreWorkflowRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\UpdateWorkflowRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNode;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\PlanLimitGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowView);

        $workflows = $workspace->workflows()
            ->visible()
            ->latest()
            ->withCount('nodes')
            ->withExists(['favoritedByUsers as is_favorite' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->get();

        $nodeTypesByWorkflow = WorkflowNode::query()
            ->whereIn('workflow_id', $workflows->pluck('id'))
            ->select('workflow_id', 'type')
            ->distinct()
            ->get()
            ->groupBy('workflow_id')
            ->map(fn ($rows) => $rows->pluck('type')->values()->all());

        $lastRunAtByWorkflow = Run::query()
            ->whereIn('workflow_id', $workflows->pluck('id'))
            ->selectRaw('workflow_id, MAX(COALESCE(finished_at, started_at, created_at)) as last_run_at')
            ->groupBy('workflow_id')
            ->pluck('last_run_at', 'workflow_id');

        $workflows->each(function (Workflow $workflow) use ($nodeTypesByWorkflow, $lastRunAtByWorkflow): void {
            $workflow->setAttribute('node_types', $nodeTypesByWorkflow->get($workflow->id, []));
            $workflow->setAttribute('last_run_at', $lastRunAtByWorkflow->get($workflow->id));
        });

        return ApiResponse::success([
            'workflows' => WorkflowResource::collection($workflows),
        ]);
    }

    public function store(StoreWorkflowRequest $request, Workspace $workspace, PlanLimitGate $limits)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $limits->assertCanCreate($workspace, PlanLimit::Workflows);

        $workflow = $workspace->workflows()->create([
            ...$request->validated(),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')).'-'.Str::random(6),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['workflow' => WorkflowResource::make($workflow)], 'Workflow created successfully.');
    }

    public function show(Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowView);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        return ApiResponse::success([
            'workflow' => WorkflowResource::make($workflow->load(['nodes', 'edges'])),
        ]);
    }

    public function update(UpdateWorkflowRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $workflow->update($request->validated());

        return ApiResponse::success(['workflow' => WorkflowResource::make($workflow)], 'Workflow updated successfully.');
    }

    public function destroy(Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $workflow->delete();

        return ApiResponse::noContent();
    }

    /**
     * `destroy` only soft-deletes, so the trash is every trashed workflow:
     * most recently trashed first.
     */
    public function trash(Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowView);

        $workflows = $workspace->workflows()
            ->onlyTrashed()
            ->visible()
            ->latest('deleted_at')
            ->get();

        return ApiResponse::success([
            'workflows' => WorkflowResource::collection($workflows),
        ]);
    }

    /**
     * A trashed workflow stops counting toward `PlanLimit::Workflows`, so
     * bringing one back has to fit under the cap like creating one would.
     */
    public function restore(Workspace $workspace, Workflow $workflow, PlanLimitGate $limits)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_unless($workflow->trashed(), 404);
        $limits->assertCanCreate($workspace, PlanLimit::Workflows);

        $workflow->restore();

        return ApiResponse::success(['workflow' => WorkflowResource::make($workflow)], 'Workflow restored successfully.');
    }

    /**
     * Only from the trash. Takes the hidden loop-mode children with it:
     * `LoopModeCompiler` names them `loop-{id}-{node}`, and nothing else
     * would ever clean them up once their parent is gone.
     */
    public function forceDestroy(Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_unless($workflow->trashed(), 404);

        DB::transaction(function () use ($workspace, $workflow): void {
            $workspace->workflows()
                ->withTrashed()
                ->where('is_internal', true)
                ->where('slug', 'like', "loop-{$workflow->id}-%")
                ->get()
                ->each->forceDelete();

            $workflow->forceDelete();
        });

        return ApiResponse::noContent();
    }

    /**
     * Copy a workflow into a new draft in the same workspace: name suffixed
     * with "(copy)", same folder, current graph carried over as-is (unlike
     * `WorkflowTemplate::storeFromWorkflow()`, credentials don't need
     * stripping here since the copy never leaves the source workspace).
     */
    public function duplicate(Workspace $workspace, Workflow $workflow, PlanLimitGate $limits)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        $limits->assertCanCreate($workspace, PlanLimit::Workflows);

        $name = "{$workflow->name} (copy)";

        $duplicate = $workspace->workflows()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'folder_id' => $workflow->folder_id,
            'description' => $workflow->description,
            'created_by' => request()->user()->id,
        ]);

        $duplicate->replaceGraph($workflow->editorGraph());

        return ApiResponse::created(['workflow' => WorkflowResource::make($duplicate->load(['nodes', 'edges']))], 'Workflow duplicated successfully.');
    }
}
