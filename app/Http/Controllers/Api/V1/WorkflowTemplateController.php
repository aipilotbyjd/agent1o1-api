<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WorkflowTemplate\StoreWorkflowTemplateRequest;
use App\Http\Resources\V1\WorkflowResource;
use App\Http\Resources\V1\WorkflowTemplateResource;
use App\Models\WorkflowTemplate;
use App\Models\Workspace;
use App\Services\WorkflowTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowTemplateController extends Controller
{
    public function __construct(private readonly WorkflowTemplateService $templateService) {}

    public function index(Request $request): JsonResponse
    {
        $templates = WorkflowTemplate::query()
            ->where('is_active', true)
            ->when($request->query('search'), fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->orderBy('sort_order')
            ->orderByDesc('usage_count')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return $this->paginatedResponse('Workflow templates retrieved.', WorkflowTemplateResource::collection($templates));
    }

    public function show(Request $request, WorkflowTemplate $workflowTemplate): JsonResponse
    {
        if (! $workflowTemplate->is_active) {
            return $this->errorResponse('Workflow template not found.', 404);
        }

        return $this->successResponse('Workflow template retrieved.', (new WorkflowTemplateResource($workflowTemplate))->detailed());
    }

    public function store(StoreWorkflowTemplateRequest $request): JsonResponse
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $data = $request->validated();
        $data['slug'] = $this->generateSlug($data['name']);
        $data['nodes_data'] = $data['nodes'];
        $data['edges_data'] = $data['edges'] ?? [];

        $template = WorkflowTemplate::create($data);

        return $this->successResponse('Workflow template created.', (new WorkflowTemplateResource($template))->detailed(), 201);
    }

    public function destroy(Request $request, WorkflowTemplate $workflowTemplate): JsonResponse
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $workflowTemplate->delete();

        return $this->successResponse('Workflow template deleted.');
    }

    public function deploy(Request $request, Workspace $workspace, WorkflowTemplate $workflowTemplate): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        if (! $workflowTemplate->is_active) {
            return $this->errorResponse('Workflow template not found.', 404);
        }

        $workflow = $this->templateService->deployToWorkspace($workflowTemplate, $workspace, $request->user());

        return $this->successResponse('Workflow created from template.', new WorkflowResource($workflow), 201);
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
        $i = 1;

        while (WorkflowTemplate::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
