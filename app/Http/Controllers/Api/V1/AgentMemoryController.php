<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AgentMemory\StoreAgentMemoryRequest;
use App\Http\Resources\V1\AgentMemoryResource;
use App\Models\Agent;
use App\Models\AgentMemory;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages an agent's persistent memory — key/value facts injected into the
 * agent's context so it can recall information across runs. Memories are either
 * agent-wide (user_id null) or scoped to the requesting user.
 */
class AgentMemoryController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $userId = $request->user()->id;

        $memories = $agent->memories()
            ->when($request->query('scope') === 'user', fn ($q) => $q->where('user_id', $userId))
            ->when($request->query('scope') === 'agent', fn ($q) => $q->whereNull('user_id'))
            ->when($request->query('scope') === null, fn ($q) => $q->where(fn ($sub) => $sub->whereNull('user_id')->orWhere('user_id', $userId)))
            ->latest('updated_at')
            ->get();

        return $this->successResponse('Memories retrieved.', AgentMemoryResource::collection($memories));
    }

    /** Upsert a memory by key within its scope (agent-wide or per-user). */
    public function store(StoreAgentMemoryRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $userId = $request->validated('scope') === 'user' ? $request->user()->id : null;

        $memory = $agent->memories()->updateOrCreate(
            ['key' => $request->validated('key'), 'user_id' => $userId],
            [
                'workspace_id' => $workspace->id,
                'value' => $request->validated('value'),
                'type' => $request->validated('type') ?? 'fact',
                'metadata' => $request->validated('metadata'),
            ],
        );

        return $this->successResponse('Memory saved.', new AgentMemoryResource($memory), $memory->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, AgentMemory $memory): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        if ($memory->agent_id !== $agent->id) {
            return $this->errorResponse('Memory not found.', 404);
        }

        $memory->delete();

        return $this->successResponse('Memory deleted.');
    }

    /** Clear all memories for the agent, optionally limited to a scope. */
    public function clear(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $deleted = $agent->memories()
            ->when($request->query('scope') === 'user', fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($request->query('scope') === 'agent', fn ($q) => $q->whereNull('user_id'))
            ->delete();

        return $this->successResponse('Memories cleared.', ['deleted' => $deleted]);
    }
}
