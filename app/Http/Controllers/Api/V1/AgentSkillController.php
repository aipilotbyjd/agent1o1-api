<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AgentSkill\GenerateAgentSkillRequest;
use App\Http\Requests\Api\V1\AgentSkill\StoreAgentSkillRequest;
use App\Http\Requests\Api\V1\AgentSkill\StoreReferenceRequest;
use App\Http\Requests\Api\V1\AgentSkill\StoreScriptRequest;
use App\Http\Requests\Api\V1\AgentSkill\UpdateAgentSkillRequest;
use App\Http\Requests\Api\V1\AgentSkill\UpdateReferenceRequest;
use App\Http\Requests\Api\V1\AgentSkill\UpdateScriptRequest;
use App\Http\Resources\V1\AgentSkillReferenceResource;
use App\Http\Resources\V1\AgentSkillResource;
use App\Http\Resources\V1\AgentSkillScriptResource;
use App\Models\AgentSkill;
use App\Models\Workspace;
use App\Services\AgentSkill\SkillGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentSkillController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $skills = $workspace->agentSkills()
            ->with('creator')
            ->withCount(['references', 'scripts'])
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'ilike', "%{$search}%"))
            ->when($request->query('is_shared') !== null, fn ($q) => $q->where('is_shared', $request->boolean('is_shared')))
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->latest()
            ->paginate((int) $request->query('per_page', 25));

        return $this->paginatedResponse('Agent skills retrieved.', AgentSkillResource::collection($skills));
    }

    public function generate(GenerateAgentSkillRequest $request, Workspace $workspace, SkillGenerationService $service): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentCreate)) {
            return $denied;
        }

        $draft = $service->generate($workspace, $request->validated('prompt'), $request->user());

        return $this->successResponse('Skill draft generated.', $draft);
    }

    public function store(StoreAgentSkillRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentCreate)) {
            return $denied;
        }

        $data = $request->validated();

        $skill = $workspace->agentSkills()->create([
            'created_by' => $request->user()->id,
            'name' => $data['name'],
            'slug' => $this->generateSlug($workspace, $data['name']),
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
            'tags' => $data['tags'] ?? null,
            'instructions' => $data['instructions'],
            'is_shared' => $data['is_shared'] ?? false,
            'version' => 1,
        ]);

        return $this->successResponse('Agent skill created.', new AgentSkillResource($skill->load('creator')), 201);
    }

    public function show(Request $request, Workspace $workspace, AgentSkill $agentSkill): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse(
            'Agent skill retrieved.',
            new AgentSkillResource($agentSkill->load(['creator', 'references', 'scripts'])),
        );
    }

    public function update(UpdateAgentSkillRequest $request, Workspace $workspace, AgentSkill $agentSkill): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $data = $request->validated();

        if (isset($data['name']) && $data['name'] !== $agentSkill->name) {
            $data['slug'] = $this->generateSlug($workspace, $data['name'], $agentSkill->id);
        }

        $agentSkill->update($data);
        $agentSkill->increment('version');

        return $this->successResponse('Agent skill updated.', new AgentSkillResource($agentSkill->fresh(['creator', 'references', 'scripts'])));
    }

    public function destroy(Request $request, Workspace $workspace, AgentSkill $agentSkill): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentDelete)) {
            return $denied;
        }

        $agentSkill->delete();

        return $this->successResponse('Agent skill deleted.');
    }

    public function addReference(StoreReferenceRequest $request, Workspace $workspace, AgentSkill $agentSkill): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $reference = $agentSkill->references()->create($request->validated());

        return $this->successResponse('Reference added.', new AgentSkillReferenceResource($reference), 201);
    }

    public function updateReference(UpdateReferenceRequest $request, Workspace $workspace, AgentSkill $agentSkill, string $reference): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $model = $agentSkill->references()->findOrFail($reference);
        $model->update($request->validated());

        return $this->successResponse('Reference updated.', new AgentSkillReferenceResource($model));
    }

    public function removeReference(Request $request, Workspace $workspace, AgentSkill $agentSkill, string $reference): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $agentSkill->references()->whereKey($reference)->delete();

        return $this->successResponse('Reference removed.');
    }

    public function addScript(StoreScriptRequest $request, Workspace $workspace, AgentSkill $agentSkill): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $data = $request->validated();
        $data['language'] ??= 'php';
        $data['is_enabled'] ??= true;

        $script = $agentSkill->scripts()->create($data);

        return $this->successResponse('Script added.', new AgentSkillScriptResource($script), 201);
    }

    public function updateScript(UpdateScriptRequest $request, Workspace $workspace, AgentSkill $agentSkill, string $script): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $model = $agentSkill->scripts()->findOrFail($script);
        $model->update($request->validated());

        return $this->successResponse('Script updated.', new AgentSkillScriptResource($model));
    }

    public function removeScript(Request $request, Workspace $workspace, AgentSkill $agentSkill, string $script): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $agentSkill->scripts()->whereKey($script)->delete();

        return $this->successResponse('Script removed.');
    }

    private function generateSlug(Workspace $workspace, string $name, ?string $excludeId = null): string
    {
        $base = Str::slug($name) ?: 'skill';
        $slug = $base;
        $suffix = 1;

        while (AgentSkill::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
