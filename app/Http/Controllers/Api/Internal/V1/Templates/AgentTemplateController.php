<?php

namespace App\Http\Controllers\Api\Internal\V1\Templates;

use App\Enums\Billing\PlanLimit;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Templates\SaveAgentAsTemplateRequest;
use App\Http\Requests\Api\Internal\V1\Templates\StoreAgentTemplateRequest;
use App\Http\Requests\Api\Internal\V1\Templates\UpdateAgentTemplateRequest;
use App\Http\Requests\Api\Internal\V1\Templates\UseAgentTemplateRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentResource;
use App\Http\Resources\Api\Internal\V1\Templates\AgentTemplateResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Templates\AgentTemplate;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\WorkspaceModels;
use App\Services\Billing\PlanLimitGate;
use Illuminate\Support\Str;

class AgentTemplateController extends Controller
{
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::TemplateView);

        $templates = AgentTemplate::query()
            ->visibleTo($workspace)
            ->when(request('category'), fn ($query, $category) => $query->where('category', $category))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest()
            ->get();

        return ApiResponse::success(['agent_templates' => AgentTemplateResource::collection($templates)]);
    }

    public function store(StoreAgentTemplateRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::TemplateManage);

        $template = $workspace->agentTemplates()->create([
            ...$request->validated(),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')).'-'.Str::random(6),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['agent_template' => AgentTemplateResource::make($template)], 'Template created successfully.');
    }

    public function show(Workspace $workspace, AgentTemplate $agentTemplate)
    {
        $this->requirePermission(Permission::TemplateView);
        $this->ensureVisibleToWorkspace($workspace, $agentTemplate);

        return ApiResponse::success(['agent_template' => AgentTemplateResource::make($agentTemplate)]);
    }

    public function update(UpdateAgentTemplateRequest $request, Workspace $workspace, AgentTemplate $agentTemplate)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $agentTemplate);

        $agentTemplate->update($request->validated());

        return ApiResponse::success(['agent_template' => AgentTemplateResource::make($agentTemplate)], 'Template updated successfully.');
    }

    public function destroy(Workspace $workspace, AgentTemplate $agentTemplate)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $agentTemplate);

        $agentTemplate->delete();

        return ApiResponse::noContent();
    }

    /**
     * Snapshot an agent's instructions/model settings and attached tools
     * into a new workspace-owned template. Any `credential_id` embedded in a
     * tool binding's config is stripped so a template can never leak one
     * workspace's connector credential into another.
     */
    public function storeFromAgent(SaveAgentAsTemplateRequest $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $template = $workspace->agentTemplates()->create([
            ...$request->validated(),
            'slug' => Str::slug($request->validated('name')).'-'.Str::random(6),
            'icon' => $request->validated('icon') ?? $agent->icon,
            'color' => $request->validated('color') ?? $agent->color,
            'source_agent_id' => $agent->id,
            'config' => $this->snapshotConfig($agent),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['agent_template' => AgentTemplateResource::make($template)], 'Agent saved as template.');
    }

    /**
     * Materialize a new `Agent` from the template's config. Attached
     * workflows/skills only carry over when the referenced row also exists
     * in the target workspace: a template used across workspaces (a
     * global/public one) silently drops references that don't resolve
     * rather than failing the whole operation.
     */
    public function use(UseAgentTemplateRequest $request, Workspace $workspace, AgentTemplate $agentTemplate, PlanLimitGate $limits)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureVisibleToWorkspace($workspace, $agentTemplate);
        $limits->assertCanCreate($workspace, PlanLimit::Agents);

        $config = $agentTemplate->config;
        $name = $request->validated('name');

        $agent = $workspace->agents()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'description' => $agentTemplate->description,
            'icon' => $agentTemplate->icon,
            'color' => $agentTemplate->color,
            'instructions' => $config['instructions'] ?? '',
            'provider' => $config['provider'] ?? 'anthropic',
            'model' => $config['model'] ?? null,
            'model_catalog_id' => $request->validated('model_catalog_id') ?? $config['model_catalog_id'] ?? app(WorkspaceModels::class)->default($workspace->id)?->id,
            'temperature' => $config['temperature'] ?? null,
            'settings' => $config['settings'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        foreach ($config['tool_bindings'] ?? [] as $binding) {
            $agent->toolBindings()->create([
                'node_type' => $binding['node_type'],
                'config' => collect($binding['config'] ?? [])->except('credential_id')->all(),
                'exposed_fields' => $binding['exposed_fields'] ?? null,
            ]);
        }

        $workflowIds = $workspace->workflows()->whereIn('id', $config['workflow_ids'] ?? [])->pluck('id');
        $agent->workflows()->sync($workflowIds);

        $skillIds = $workspace->skills()->whereIn('id', $config['skill_ids'] ?? [])->pluck('id');
        $agent->skills()->sync($skillIds);

        $agentTemplate->increment('usage_count');

        return ApiResponse::created(['agent' => AgentResource::make($agent)], 'Agent created from template.');
    }

    private function ensureVisibleToWorkspace(Workspace $workspace, AgentTemplate $template): void
    {
        $visible = $template->workspace_id === $workspace->id
            || ($template->workspace_id === null && $template->isPublic());

        abort_unless($visible, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotConfig(Agent $agent): array
    {
        $agent->loadMissing(['toolBindings', 'workflows', 'skills']);

        return [
            'instructions' => $agent->instructions,
            'provider' => $agent->provider,
            'model' => $agent->model,
            'model_catalog_id' => $agent->model_catalog_id,
            'temperature' => $agent->temperature,
            'settings' => $agent->settings,
            'tool_bindings' => $agent->toolBindings
                ->map(fn ($binding): array => [
                    'node_type' => $binding->node_type,
                    'config' => collect($binding->config ?? [])->except('credential_id')->all(),
                    'exposed_fields' => $binding->exposed_fields,
                ])
                ->all(),
            'workflow_ids' => $agent->workflows->pluck('id')->all(),
            'skill_ids' => $agent->skills->pluck('id')->all(),
        ];
    }
}
