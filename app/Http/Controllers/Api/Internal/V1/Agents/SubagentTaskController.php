<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Agents\SubagentTaskResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Workspaces\Workspace;

/**
 * The subagents a conversation has started, for the chat's live status
 * cards: polled while any are still running.
 */
class SubagentTaskController extends Controller
{
    public function index(Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        return ApiResponse::success([
            'tasks' => SubagentTaskResource::collection(
                $session->subagentTasks()->with('agent:id,name,icon,color')->oldest()->get(),
            ),
        ]);
    }
}
