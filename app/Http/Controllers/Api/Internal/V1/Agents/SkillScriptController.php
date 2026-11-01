<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Agents\Concerns\GuardsSyncedSkills;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\StoreSkillScriptRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateSkillScriptRequest;
use App\Http\Resources\Api\Internal\V1\Agents\SkillScriptResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Skill;
use App\Models\Agents\SkillScript;
use App\Models\Workspaces\Workspace;

class SkillScriptController extends Controller
{
    use GuardsSyncedSkills;

    public function index(Workspace $workspace, Skill $skill)
    {
        // Script source isn't something a viewer needs; it's managed with the skill.
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skill);

        return ApiResponse::success([
            'scripts' => SkillScriptResource::collection($skill->scripts),
        ]);
    }

    public function store(StoreSkillScriptRequest $request, Workspace $workspace, Skill $skill)
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skill);
        $this->ensureNotSynced($skill);

        $script = $skill->scripts()->create($request->validated());

        return ApiResponse::created(['script' => SkillScriptResource::make($script)], 'Script created successfully.');
    }

    public function update(UpdateSkillScriptRequest $request, Workspace $workspace, Skill $skill, SkillScript $script)
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skill);
        $this->ensureNotSynced($skill);
        $this->ensureBelongsToSkill($skill, $script);

        $script->update($request->validated());

        return ApiResponse::success(['script' => SkillScriptResource::make($script->fresh())], 'Script updated successfully.');
    }

    public function destroy(Workspace $workspace, Skill $skill, SkillScript $script)
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skill);
        $this->ensureNotSynced($skill);
        $this->ensureBelongsToSkill($skill, $script);

        $script->delete();

        return ApiResponse::noContent();
    }

    private function ensureBelongsToSkill(Skill $skill, SkillScript $script): void
    {
        abort_if($script->skill_id !== $skill->id, 404);
    }
}
