<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AgentKnowledge\StoreAgentKnowledgeRequest;
use App\Http\Requests\Api\V1\AgentKnowledge\UpdateAgentKnowledgeRequest;
use App\Http\Resources\V1\AgentKnowledgeResource;
use App\Models\Agent;
use App\Models\AgentKnowledge;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Manages an agent's knowledge base — documents/snippets injected into the
 * agent's context at run time to ground its responses.
 */
class AgentKnowledgeController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $items = $agent->knowledge()
            ->when($request->query('is_active') !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->query('search'), fn ($q, $search) => $q->where('title', 'ilike', "%{$search}%"))
            ->paginate((int) $request->query('per_page', 25));

        return $this->paginatedResponse('Knowledge retrieved.', AgentKnowledgeResource::collection($items));
    }

    public function store(StoreAgentKnowledgeRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $data = $request->validated();

        $item = $agent->knowledge()->create([
            ...$data,
            'workspace_id' => $workspace->id,
            'source_type' => $data['source_type'] ?? 'text',
            'is_active' => $data['is_active'] ?? true,
            'tokens' => $this->estimateTokens($data['content']),
        ]);

        return $this->successResponse('Knowledge created.', new AgentKnowledgeResource($item), 201);
    }

    public function show(Request $request, Workspace $workspace, Agent $agent, AgentKnowledge $knowledge): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        if ($knowledge->agent_id !== $agent->id) {
            return $this->errorResponse('Knowledge not found.', 404);
        }

        return $this->successResponse('Knowledge retrieved.', new AgentKnowledgeResource($knowledge));
    }

    public function update(UpdateAgentKnowledgeRequest $request, Workspace $workspace, Agent $agent, AgentKnowledge $knowledge): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        if ($knowledge->agent_id !== $agent->id) {
            return $this->errorResponse('Knowledge not found.', 404);
        }

        $data = $request->validated();

        if (array_key_exists('content', $data)) {
            $data['tokens'] = $this->estimateTokens($data['content']);
        }

        $knowledge->update($data);

        return $this->successResponse('Knowledge updated.', new AgentKnowledgeResource($knowledge));
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, AgentKnowledge $knowledge): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        if ($knowledge->agent_id !== $agent->id) {
            return $this->errorResponse('Knowledge not found.', 404);
        }

        $knowledge->delete();

        return $this->successResponse('Knowledge deleted.');
    }

    /** Rough token estimate (~4 chars/token) used for budgeting context injection. */
    private function estimateTokens(string $content): int
    {
        return (int) ceil(Str::length($content) / 4);
    }
}
