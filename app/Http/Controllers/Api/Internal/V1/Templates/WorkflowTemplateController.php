<?php

namespace App\Http\Controllers\Api\Internal\V1\Templates;

use App\Enums\Billing\PlanLimit;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Templates\SaveWorkflowAsTemplateRequest;
use App\Http\Requests\Api\Internal\V1\Templates\StoreWorkflowTemplateRequest;
use App\Http\Requests\Api\Internal\V1\Templates\UpdateWorkflowTemplateRequest;
use App\Http\Requests\Api\Internal\V1\Templates\UseWorkflowTemplateRequest;
use App\Http\Resources\Api\Internal\V1\Templates\WorkflowTemplateResource;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Templates\WorkflowTemplate;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowEdge;
use App\Models\Workflows\WorkflowNode;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\PlanLimitGate;
use Illuminate\Support\Str;

class WorkflowTemplateController extends Controller
{
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::TemplateView);

        $templates = WorkflowTemplate::query()
            ->visibleTo($workspace)
            ->when(request('category'), fn ($query, $category) => $query->where('category', $category))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest()
            ->get();

        return ApiResponse::success(['workflow_templates' => WorkflowTemplateResource::collection($templates)]);
    }

    public function store(StoreWorkflowTemplateRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::TemplateManage);

        $template = $workspace->workflowTemplates()->create([
            ...$request->validated(),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')).'-'.Str::random(6),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['workflow_template' => WorkflowTemplateResource::make($template)], 'Template created successfully.');
    }

    public function show(Workspace $workspace, WorkflowTemplate $workflowTemplate)
    {
        $this->requirePermission(Permission::TemplateView);
        $this->ensureVisibleToWorkspace($workspace, $workflowTemplate);

        return ApiResponse::success(['workflow_template' => WorkflowTemplateResource::make($workflowTemplate)]);
    }

    public function update(UpdateWorkflowTemplateRequest $request, Workspace $workspace, WorkflowTemplate $workflowTemplate)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $workflowTemplate);

        $workflowTemplate->update($request->validated());

        return ApiResponse::success(['workflow_template' => WorkflowTemplateResource::make($workflowTemplate)], 'Template updated successfully.');
    }

    public function destroy(Workspace $workspace, WorkflowTemplate $workflowTemplate)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $workflowTemplate);

        $workflowTemplate->delete();

        return ApiResponse::noContent();
    }

    /**
     * Snapshot a workflow's current graph into a new workspace-owned
     * template. Any `credential_id` embedded in node config is stripped so a
     * template can never leak one workspace's connector credential into
     * another (see docs on `ResolvesConnectorCredential`).
     */
    public function storeFromWorkflow(SaveWorkflowAsTemplateRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $template = $workspace->workflowTemplates()->create([
            ...$request->validated(),
            'slug' => Str::slug($request->validated('name')).'-'.Str::random(6),
            'source_workflow_id' => $workflow->id,
            'graph' => $this->snapshotGraph($workflow),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['workflow_template' => WorkflowTemplateResource::make($template)], 'Workflow saved as template.');
    }

    /**
     * Materialize a new draft `Workflow` from the template's graph via
     * `Workflow::replaceGraph()`: the same method the builder canvas uses
     * to save an ordinary edit.
     */
    public function use(UseWorkflowTemplateRequest $request, Workspace $workspace, WorkflowTemplate $workflowTemplate, PlanLimitGate $limits)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureVisibleToWorkspace($workspace, $workflowTemplate);
        $limits->assertCanCreate($workspace, PlanLimit::Workflows);

        $name = $request->validated('name');

        $workflow = $workspace->workflows()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'folder_id' => $request->validated('folder_id'),
            'description' => $workflowTemplate->description,
            'created_by' => $request->user()->id,
        ]);

        $workflow->replaceGraph($workflowTemplate->graph);
        $workflowTemplate->increment('usage_count');

        return ApiResponse::created(['workflow' => WorkflowResource::make($workflow->load(['nodes', 'edges']))], 'Workflow created from template.');
    }

    private function ensureVisibleToWorkspace(Workspace $workspace, WorkflowTemplate $template): void
    {
        $visible = $template->workspace_id === $workspace->id
            || ($template->workspace_id === null && $template->isPublic());

        abort_unless($visible, 404);
    }

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    private function snapshotGraph(Workflow $workflow): array
    {
        $workflow->loadMissing(['nodes', 'edges']);

        $nodeKeysById = $workflow->nodes->pluck('key', 'id');

        $nodes = $workflow->nodes
            ->map(fn (WorkflowNode $node): array => [
                'key' => $node->key,
                'type' => $node->type,
                'config' => collect($node->config ?? [])->except('credential_id')->all(),
                'position' => $node->position,
            ])
            ->all();

        $edges = $workflow->edges
            ->map(fn (WorkflowEdge $edge): array => [
                'from' => $nodeKeysById[$edge->from_node_id],
                'to' => $nodeKeysById[$edge->to_node_id],
                'condition' => $edge->condition,
            ])
            ->all();

        return ['nodes' => $nodes, 'edges' => $edges];
    }
}
