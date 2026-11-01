<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Agents\Concerns\GuardsSyncedSkills;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\StoreSkillReferenceRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateSkillReferenceRequest;
use App\Http\Resources\Api\Internal\V1\Agents\SkillReferenceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Skill;
use App\Models\Agents\SkillReference;
use App\Models\Workspaces\Workspace;

class SkillReferenceController extends Controller
{
    use GuardsSyncedSkills;

    public function index(Workspace $workspace, Skill $skill)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $skill);

        return ApiResponse::success([
            'references' => SkillReferenceResource::collection($skill->references),
        ]);
    }

    public function store(StoreSkillReferenceRequest $request, Workspace $workspace, Skill $skill)
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skill);
        $this->ensureNotSynced($skill);

        $reference = $skill->references()->create($request->validated());
        $skill->bumpVersion();

        return ApiResponse::created(['reference' => SkillReferenceResource::make($reference)], 'Reference created successfully.');
    }

    public function update(UpdateSkillReferenceRequest $request, Workspace $workspace, Skill $skill, SkillReference $reference)
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skill);
        $this->ensureNotSynced($skill);
        $this->ensureBelongsToSkill($skill, $reference);

        $reference->update($request->validated());

        if ($reference->wasChanged(['title', 'content', 'sort_order'])) {
            $skill->bumpVersion();
        }

        return ApiResponse::success(['reference' => SkillReferenceResource::make($reference->fresh())], 'Reference updated successfully.');
    }

    public function destroy(Workspace $workspace, Skill $skill, SkillReference $reference)
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skill);
        $this->ensureNotSynced($skill);
        $this->ensureBelongsToSkill($skill, $reference);

        $reference->delete();
        $skill->bumpVersion();

        return ApiResponse::noContent();
    }

    private function ensureBelongsToSkill(Skill $skill, SkillReference $reference): void
    {
        abort_if($reference->skill_id !== $skill->id, 404);
    }
}
