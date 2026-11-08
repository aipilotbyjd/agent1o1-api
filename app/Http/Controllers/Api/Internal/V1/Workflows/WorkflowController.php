<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Billing\PlanLimit;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\IndexWorkflowsRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\StoreWorkflowRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\UpdateWorkflowRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNode;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\PlanLimitGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowController extends Controller
{
    /**
     * The workflows page: filtered, sorted and paged on the server, since a
     * workspace can hold thousands. Paged by number rather than cursor
     * because it sorts by computed columns (last run, node count) a cursor
     * can't follow, and the page shows how many match. The node-type lookup
     * is keyed on the page's ids only, so a request costs the same however
     * large the workspace is.
     */
    public function index(IndexWorkflowsRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowView);

        $userId = $request->user()->id;
        $search = trim((string) $request->validated('search', ''));

        $workflows = $workspace->workflows()
            ->visible()
            ->with('tags')
            ->withCount('nodes')
            ->withExists(['favoritedByUsers as is_favorite' => fn ($query) => $query->where('user_id', $userId)])
            ->addSelect(['last_run_at' => Run::query()
                ->selectRaw('MAX(COALESCE(finished_at, started_at, created_at))')
                ->whereColumn('runs.workflow_id', 'workflows.id'),
            ])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('description', "%{$search}%")
                ->orWhereHas('tags', fn (Builder $tags) => $tags->whereLike('name', "%{$search}%"))
                ->orWhereHas('nodes', fn (Builder $nodes) => $nodes->whereLike('type', "%{$search}%"))))
            ->when($request->validated('status') === 'published', fn (Builder $query) => $query->whereNotNull('current_version_id'))
            ->when($request->validated('status') === 'draft', fn (Builder $query) => $query->whereNull('current_version_id'))
            ->when($request->boolean('favorites'), fn (Builder $query) => $query->whereHas('favoritedByUsers', fn (Builder $users) => $users->where('users.id', $userId)))
            ->when($request->validated('tag_id'), fn (Builder $query, string $tagId) => $query->whereHas('tags', fn (Builder $tags) => $tags->where('tags.id', $tagId)));

        match ($request->validated('sort', 'updated')) {
            'name' => $workflows->orderBy('name'),
            'nodes' => $workflows->orderByDesc('nodes_count'),
            'last_run' => $workflows->orderByRaw('last_run_at desc nulls last'),
            default => $workflows->orderByDesc('updated_at'),
        };

        $page = $workflows
            ->orderBy('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $nodeTypesByWorkflow = WorkflowNode::query()
            ->whereIn('workflow_id', $page->getCollection()->pluck('id'))
            ->select('workflow_id', 'type')
            ->distinct()
            ->get()
            ->groupBy('workflow_id')
            ->map(fn ($rows) => $rows->pluck('type')->values()->all());

        $page->getCollection()->each(fn (Workflow $workflow) => $workflow->setAttribute('node_types', $nodeTypesByWorkflow->get($workflow->id, [])));

        return ApiResponse::paginated(WorkflowResource::collection($page));
    }

    /**
     * What the workflows page's stat cards show, without loading the list.
     */
    public function summary(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowView);

        $visible = $workspace->workflows()->visible();

        return ApiResponse::success([
            'total' => (clone $visible)->count(),
            'published' => (clone $visible)->whereNotNull('current_version_id')->count(),
            'favorites' => (clone $visible)
                ->whereHas('favoritedByUsers', fn (Builder $users) => $users->where('users.id', $request->user()->id))
                ->count(),
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
    public function trash(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowView);

        $workflows = $workspace->workflows()
            ->onlyTrashed()
            ->visible()
            ->latest('deleted_at')
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request))
            ->withQueryString();

        return ApiResponse::cursorPaginated(WorkflowResource::collection($workflows));
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
