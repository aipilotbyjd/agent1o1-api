<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agent\AttachSkillRequest;
use App\Http\Requests\Api\V1\Agent\DestroyAgentRequest;
use App\Http\Requests\Api\V1\Agent\DuplicateAgentRequest;
use App\Http\Requests\Api\V1\Agent\IndexAgentRequest;
use App\Http\Requests\Api\V1\Agent\ShowAgentRequest;
use App\Http\Requests\Api\V1\Agent\StoreAgentRequest;
use App\Http\Requests\Api\V1\Agent\UpdateAgentRequest;
use App\Http\Resources\V1\AgentResource;
use App\Models\Agent;
use App\Models\Workspace;
use App\Services\AgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function __construct(private readonly AgentService $agentService) {}

    public function index(IndexAgentRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $sortBy = in_array($request->query('sort_by'), ['name', 'created_at', 'updated_at'], true)
            ? $request->query('sort_by')
            : 'created_at';
        $sortDir = $request->query('sort_dir') === 'asc' ? 'asc' : 'desc';

        $agents = $workspace->agents()
            ->withCount(['skills', 'conversations'])
            ->with('toolConfigs')
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'ilike', "%{$search}%"))
            ->when($request->query('is_active') !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->orderBy($sortBy, $sortDir)
            ->paginate((int) $request->query('per_page', 25));

        return $this->paginatedResponse('Agents retrieved.', AgentResource::collection($agents));
    }

    public function store(StoreAgentRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentCreate)) {
            return $denied;
        }

        $agent = $this->agentService->create($workspace, $request->user(), $request->validated());

        return $this->successResponse('Agent created.', new AgentResource($agent), 201);
    }

    public function show(ShowAgentRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse(
            'Agent retrieved.',
            new AgentResource($agent->load(['creator', 'toolConfigs', 'skills.references', 'skills.scripts', 'triggers'])),
        );
    }

    public function update(UpdateAgentRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $agent = $this->agentService->update($agent, $request->validated());

        return $this->successResponse('Agent updated.', new AgentResource($agent));
    }

    public function destroy(DestroyAgentRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentDelete)) {
            return $denied;
        }

        $this->agentService->delete($agent);

        return $this->successResponse('Agent deleted.');
    }

    public function duplicate(DuplicateAgentRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentCreate)) {
            return $denied;
        }

        $copy = $this->agentService->duplicate($agent, $request->user());

        return $this->successResponse('Agent duplicated.', new AgentResource($copy), 201);
    }

    public function attachSkill(AttachSkillRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        // Skills are workspace-scoped; only attach skills owned by this workspace.
        $skill = $workspace->agentSkills()->findOrFail($request->validated('skill_id'));

        $agent->skills()->syncWithoutDetaching([
            $skill->id => ['sort_order' => $request->validated('sort_order') ?? 0],
        ]);

        return $this->successResponse('Skill attached.', new AgentResource($agent->fresh('skills')));
    }

    public function detachSkill(Request $request, Workspace $workspace, Agent $agent, string $skillId): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $agent->skills()->detach($skillId);

        return $this->successResponse('Skill detached.');
    }
}
