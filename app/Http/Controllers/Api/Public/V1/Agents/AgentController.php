<?php

namespace App\Http\Controllers\Api\Public\V1\Agents;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Public\V1\AgentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use Illuminate\Http\Request;

/**
 * Discovery for the invoke endpoints: a caller holding `agents:invoke` needs
 * to know which agents exist and what their ids are before it can start a
 * session with one. Read-only: authoring an agent belongs to the internal
 * API.
 */
class AgentController extends Controller
{
    public function index(Request $request)
    {
        return ApiResponse::success([
            'agents' => AgentResource::collection(
                $this->apiKeyWorkspace($request)->agents()->latest()->get(),
            ),
        ]);
    }

    public function show(Request $request, Agent $agent)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $agent);

        return ApiResponse::success(['agent' => AgentResource::make($agent)]);
    }
}
