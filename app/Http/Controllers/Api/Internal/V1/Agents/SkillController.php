<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Agents\Concerns\GuardsSyncedSkills;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\PublishSkillRequest;
use App\Http\Requests\Api\Internal\V1\Agents\StoreSkillRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateSkillRequest;
use App\Http\Resources\Api\Internal\V1\Agents\SkillResource;
use App\Http\Resources\Api\Internal\V1\Agents\SkillSourceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Skill;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\Skills\SkillPublishing;
use App\Services\Agents\Skills\SkillSources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SkillController extends Controller
{
    use GuardsSyncedSkills;

    /**
     * What a synced skill may still change here: how it looks and who sees
     * it, none of which its repository says.
     */
    private const array SYNCED_EDITABLE = ['category', 'icon', 'color', 'tags', 'is_shared'];

    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        return ApiResponse::success([
            'skills' => SkillResource::collection($workspace->skills()->with('source')->latest()->get()),
        ]);
    }

    public function store(StoreSkillRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentSkillManage);

        // References and scripts may come along with the skill (a generated
        // draft carries both), so the whole thing saves or nothing does.
        $skill = DB::transaction(function () use ($request, $workspace): Skill {
            $skill = $workspace->skills()->create([
                ...Arr::except($request->validated(), ['references', 'scripts']),
                'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')).'-'.Str::random(6),
                'created_by' => $request->user()->id,
            ]);

            foreach (array_values($request->validated('references', [])) as $sortOrder => $reference) {
                $skill->references()->create([...$reference, 'sort_order' => $sortOrder]);
            }

            foreach ($request->validated('scripts', []) as $script) {
                $skill->scripts()->create($script);
            }

            return $skill;
        });

        return ApiResponse::created(['skill' => SkillResource::make($skill)], 'Skill created successfully.');
    }

    public function copy(Request $request, Workspace $workspace, Skill $skill, SkillPublishing $publishing): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skill);

        return ApiResponse::created(['skill' => SkillResource::make($publishing->copy($skill, $request->user()))], 'Editable copy created.');
    }

    public function publish(PublishSkillRequest $request, Workspace $workspace, Skill $skill, SkillPublishing $publishing, SkillSources $sources): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skill);
        try {
            $source = $publishing->publish($workspace, $request->user(), $skill, $request->validated());
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
        $sources->queueSync($source, force: true);

        return ApiResponse::created(['skill' => SkillResource::make($skill->fresh()), 'source' => ($fresh = $source->fresh()) ? SkillSourceResource::make($fresh) : null], 'Publishing skill to GitHub.');
    }

    public function show(Workspace $workspace, Skill $skill)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $skill);

        return ApiResponse::success(['skill' => SkillResource::make($skill)]);
    }

    public function update(UpdateSkillRequest $request, Workspace $workspace, Skill $skill)
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skill);

        if ($skill->isSynced() && array_diff(array_keys($request->validated()), self::SYNCED_EDITABLE) !== []) {
            $this->ensureNotSynced($skill);
        }

        // A version bump is a signal to callers that an in-flight session's
        // Skill context may have changed: bump it only when the instructions
        // actually change, not on cosmetic fields like color/icon. Set in the
        // same save as the edit; `version` isn't fillable.
        $skill->fill($request->validated());

        if ($skill->isDirty('instructions')) {
            $skill->version++;
        }

        $skill->save();

        return ApiResponse::success(['skill' => SkillResource::make($skill->fresh())], 'Skill updated successfully.');
    }

    public function destroy(Workspace $workspace, Skill $skill)
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skill);
        $this->ensureNotSynced($skill);

        $skill->delete();

        return ApiResponse::noContent();
    }
}
