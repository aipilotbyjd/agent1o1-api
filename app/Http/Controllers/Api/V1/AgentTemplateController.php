<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AgentTemplate\StoreAgentTemplateRequest;
use App\Http\Requests\Api\V1\AgentTemplate\UpdateAgentTemplateRequest;
use App\Http\Resources\V1\AgentResource;
use App\Http\Resources\V1\AgentTemplateResource;
use App\Models\AgentTemplate;
use App\Models\Workspace;
use App\Services\AgentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentTemplateController extends Controller
{
    public function __construct(private readonly AgentTemplateService $templateService) {}

    public function index(Request $request): JsonResponse
    {
        $templates = AgentTemplate::query()
            ->where('is_active', true)
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'ilike', "%{$search}%"))
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->orderBy('sort_order')
            ->orderByDesc('usage_count')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return $this->paginatedResponse('Agent templates retrieved.', AgentTemplateResource::collection($templates));
    }

    public function show(Request $request, AgentTemplate $agentTemplate): JsonResponse
    {
        if (! $agentTemplate->is_active) {
            return $this->errorResponse('Agent template not found.', 404);
        }

        return $this->successResponse(
            'Agent template retrieved.',
            (new AgentTemplateResource($agentTemplate))->detailed(),
        );
    }

    public function store(StoreAgentTemplateRequest $request): JsonResponse
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $data = $request->validated();
        $data['slug'] = $this->generateSlug($data['name']);

        $template = AgentTemplate::create($data);

        return $this->successResponse('Agent template created.', (new AgentTemplateResource($template))->detailed(), 201);
    }

    public function update(UpdateAgentTemplateRequest $request, AgentTemplate $agentTemplate): JsonResponse
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $agentTemplate->update($request->validated());

        return $this->successResponse('Agent template updated.', (new AgentTemplateResource($agentTemplate))->detailed());
    }

    public function destroy(Request $request, AgentTemplate $agentTemplate): JsonResponse
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $agentTemplate->delete();

        return $this->successResponse('Agent template deleted.');
    }

    public function deploy(Request $request, Workspace $workspace, AgentTemplate $agentTemplate): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentCreate)) {
            return $denied;
        }

        if (! $agentTemplate->is_active) {
            return $this->errorResponse('Agent template not found.', 404);
        }

        $agent = $this->templateService->deployToWorkspace($agentTemplate, $workspace, $request->user());

        return $this->successResponse('Agent deployed from template.', new AgentResource($agent), 201);
    }

    private function requireAdmin(Request $request): ?JsonResponse
    {
        if (! $request->user()?->can('platformAdmin')) {
            return $this->errorResponse('Forbidden.', 403);
        }

        return null;
    }

    private function generateSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'template';
        $slug = $base;
        $suffix = 1;

        while (AgentTemplate::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
