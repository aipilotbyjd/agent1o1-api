<?php

namespace App\Http\Controllers\Api\Internal\V1\Templates;

use App\Enums\Billing\PlanLimit;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Templates\StoreTemplateCollectionRequest;
use App\Http\Requests\Api\Internal\V1\Templates\UpdateTemplateCollectionRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentResource;
use App\Http\Resources\Api\Internal\V1\Templates\TemplateCollectionResource;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\TemplateCollection;
use App\Models\Templates\WorkflowTemplate;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\WorkspaceModels;
use App\Services\Billing\PlanLimitGate;
use Illuminate\Support\Str;

class TemplateCollectionController extends Controller
{
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::TemplateView);

        $collections = TemplateCollection::query()
            ->visibleTo($workspace)
            ->withCount('items')
            ->when(request('category'), fn ($query, $category) => $query->where('category', $category))
            ->latest()
            ->get();

        return ApiResponse::success(['template_collections' => TemplateCollectionResource::collection($collections)]);
    }

    public function store(StoreTemplateCollectionRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::TemplateManage);

        $collection = $workspace->templateCollections()->create([
            ...$request->validated(),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')).'-'.Str::random(6),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['template_collection' => TemplateCollectionResource::make($collection)], 'Collection created successfully.');
    }

    public function show(Workspace $workspace, TemplateCollection $templateCollection)
    {
        $this->requirePermission(Permission::TemplateView);
        $this->ensureVisibleToWorkspace($workspace, $templateCollection);

        return ApiResponse::success([
            'template_collection' => TemplateCollectionResource::make($templateCollection->load('items.templatable')),
        ]);
    }

    public function update(UpdateTemplateCollectionRequest $request, Workspace $workspace, TemplateCollection $templateCollection)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $templateCollection);

        $templateCollection->update($request->validated());

        return ApiResponse::success(['template_collection' => TemplateCollectionResource::make($templateCollection)], 'Collection updated successfully.');
    }

    public function destroy(Workspace $workspace, TemplateCollection $templateCollection)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $templateCollection);

        $templateCollection->delete();

        return ApiResponse::noContent();
    }

    /**
     * Bulk-instantiate every template in the pack in one call (a workflow
     * per `WorkflowTemplate` item, an agent per `AgentTemplate` item) each
     * named after its own template.
     */
    public function use(Workspace $workspace, TemplateCollection $templateCollection, PlanLimitGate $limits)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureVisibleToWorkspace($workspace, $templateCollection);

        $items = $templateCollection->items()->with('templatable')->get();

        // Assert the whole pack up front rather than per item: instantiation
        // isn't transactional, so a cap hit mid-loop would leave half the
        // collection created and the rest not.
        $limits->assertCanCreate($workspace, PlanLimit::Workflows, $items->filter(
            fn ($item): bool => $item->templatable instanceof WorkflowTemplate,
        )->count());

        $limits->assertCanCreate($workspace, PlanLimit::Agents, $items->filter(
            fn ($item): bool => $item->templatable instanceof AgentTemplate,
        )->count());

        $workflows = collect();
        $agents = collect();

        foreach ($items as $item) {
            if ($item->templatable instanceof WorkflowTemplate) {
                $workflows->push($this->instantiateWorkflow($workspace, $item->templatable));
            } elseif ($item->templatable instanceof AgentTemplate) {
                $agents->push($this->instantiateAgent($workspace, $item->templatable));
            }
        }

        return ApiResponse::created([
            'workflows' => WorkflowResource::collection($workflows),
            'agents' => AgentResource::collection($agents),
        ], 'Collection instantiated.');
    }

    private function instantiateWorkflow(Workspace $workspace, WorkflowTemplate $template): Workflow
    {
        $workflow = $workspace->workflows()->create([
            'name' => $template->name,
            'slug' => Str::slug($template->name).'-'.Str::random(6),
            'description' => $template->description,
            'created_by' => auth()->id(),
        ]);

        $workflow->replaceGraph($template->graph);
        $template->increment('usage_count');

        return $workflow->load(['nodes', 'edges']);
    }

    private function instantiateAgent(Workspace $workspace, AgentTemplate $template): Agent
    {
        $config = $template->config;

        $agent = $workspace->agents()->create([
            'name' => $template->name,
            'slug' => Str::slug($template->name).'-'.Str::random(6),
            'description' => $template->description,
            'instructions' => $config['instructions'] ?? '',
            'provider' => $config['provider'] ?? 'anthropic',
            'model' => $config['model'] ?? null,
            'model_catalog_id' => $config['model_catalog_id'] ?? app(WorkspaceModels::class)->default($workspace->id)?->id,
            'temperature' => $config['temperature'] ?? null,
            'settings' => $config['settings'] ?? null,
            'created_by' => auth()->id(),
        ]);

        foreach ($config['tool_bindings'] ?? [] as $binding) {
            $agent->toolBindings()->create([
                'node_type' => $binding['node_type'],
                'config' => collect($binding['config'] ?? [])->except('credential_id')->all(),
                'exposed_fields' => $binding['exposed_fields'] ?? null,
            ]);
        }

        $agent->workflows()->sync($workspace->workflows()->whereIn('id', $config['workflow_ids'] ?? [])->pluck('id'));
        $agent->skills()->sync($workspace->skills()->whereIn('id', $config['skill_ids'] ?? [])->pluck('id'));

        $template->increment('usage_count');

        return $agent;
    }

    private function ensureVisibleToWorkspace(Workspace $workspace, TemplateCollection $collection): void
    {
        $visible = $collection->workspace_id === $workspace->id
            || ($collection->workspace_id === null && $collection->isPublic());

        abort_unless($visible, 404);
    }
}
