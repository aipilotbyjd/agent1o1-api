<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\Approvals\TrustRules;
use Illuminate\Http\Request;

/**
 * Tools this agent has earned trust on (ones people keep approving as-is)
 * and applying a suggestion, which sets that tool to run without asking.
 * See `TrustRules`.
 */
class AgentTrustController extends Controller
{
    public function __construct(private readonly TrustRules $trust) {}

    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success(['suggestions' => $this->trust->suggestionsFor($agent)]);
    }

    public function apply(Request $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $toolName = $request->validate(['tool_name' => ['required', 'string', 'max:255']])['tool_name'];

        abort_unless($this->trust->allowAlways($agent, $toolName), 404, 'No tool with that name is attached to this agent.');

        return ApiResponse::success(['suggestions' => $this->trust->suggestionsFor($agent)], 'The tool now runs without asking.');
    }
}
