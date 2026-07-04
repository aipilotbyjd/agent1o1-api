<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AgentTrigger\StoreAgentTriggerRequest;
use App\Http\Requests\Api\V1\AgentTrigger\UpdateAgentTriggerRequest;
use App\Http\Resources\V1\AgentTriggerResource;
use App\Jobs\RunAgentJob;
use App\Models\Agent;
use App\Models\AgentTrigger;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentTriggerController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse(
            'Agent triggers retrieved.',
            AgentTriggerResource::collection($agent->triggers()->latest()->get()),
        );
    }

    public function store(StoreAgentTriggerRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $trigger = $agent->triggers()->create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
            'is_active' => $request->validated('is_active') ?? true,
        ]);

        return $this->successResponse('Agent trigger created.', new AgentTriggerResource($trigger), 201);
    }

    public function update(UpdateAgentTriggerRequest $request, Workspace $workspace, Agent $agent, AgentTrigger $trigger): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $trigger->update($request->validated());

        return $this->successResponse('Agent trigger updated.', new AgentTriggerResource($trigger));
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, AgentTrigger $trigger): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $trigger->delete();

        return $this->successResponse('Agent trigger deleted.');
    }

    public function fire(Request $request, Workspace $workspace, Agent $agent, AgentTrigger $trigger): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentRun)) {
            return $denied;
        }

        $message = $trigger->initial_message ?? $request->input('message', '');

        RunAgentJob::dispatch($agent->id, $message, $trigger->id, [
            'fired_by' => $request->user()->id,
        ]);

        return $this->successResponse('Trigger fired. Agent will run in the background.', null, 202);
    }
}
