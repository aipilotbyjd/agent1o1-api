<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Agents\SkillResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\Skill;
use App\Models\Workspaces\Workspace;

class AgentSkillController extends Controller
{
    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success(['skills' => SkillResource::collection($agent->skills)]);
    }

    public function store(Workspace $workspace, Agent $agent, Skill $skill)
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        $this->ensureBelongsToWorkspace($workspace, $skill);

        $agent->skills()->syncWithoutDetaching([$skill->id]);

        return ApiResponse::success(['skills' => SkillResource::collection($agent->skills()->get())], 'Skill attached successfully.');
    }

    public function destroy(Workspace $workspace, Agent $agent, Skill $skill)
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        $this->ensureBelongsToWorkspace($workspace, $skill);

        $agent->skills()->detach($skill->id);

        return ApiResponse::noContent();
    }
}
