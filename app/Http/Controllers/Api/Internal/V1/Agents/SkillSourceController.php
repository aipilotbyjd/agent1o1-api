<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Agents\SkillSourceStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\ApplySkillUpstreamRequest;
use App\Http\Requests\Api\Internal\V1\Agents\ExportSkillRequest;
use App\Http\Requests\Api\Internal\V1\Agents\ForkSkillSourceRequest;
use App\Http\Requests\Api\Internal\V1\Agents\ResolveSkillSourceRequest;
use App\Http\Requests\Api\Internal\V1\Agents\SkillRepositoryAccessRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateSkillSourceRequest;
use App\Http\Resources\Api\Internal\V1\Agents\SkillSourceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\SkillSource;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\Skills\SkillForks;
use App\Services\Agents\Skills\SkillPublishing;
use App\Services\Agents\Skills\SkillSources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * GitHub repositories the workspace's skills are synced from. Picking an
 * account and a repository reuses the knowledge base's `sources/apps` and
 * `sources/options?type=github`.
 */
class SkillSourceController extends Controller
{
    public function __construct(private readonly SkillSources $sources) {}

    public function index(Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AgentView);

        return ApiResponse::success([
            'sources' => SkillSourceResource::collection($workspace->skillSources()->with(['credential:id,name', 'skills.references', 'skills.scripts'])->latest()->get()),
        ]);
    }

    /**
     * The skills a repository holds, read without connecting it.
     */
    public function preview(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);

        try {
            $preview = $this->sources->preview($workspace, $request->user(), $request->only(['repo', 'branch', 'path', 'credential_id']));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(['preview' => $preview]);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);

        $source = $this->sources->create($workspace, $request->user(), $request->only(['repo', 'branch', 'path', 'credential_id', 'is_shared', 'two_way']));

        return ApiResponse::created(['source' => SkillSourceResource::make($source->load('credential:id,name'))], 'Repository connected. Syncing now.');
    }

    public function access(SkillRepositoryAccessRequest $request, Workspace $workspace, SkillPublishing $publishing): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);
        try {
            return ApiResponse::success(['access' => $publishing->access($workspace, $request->user(), $request->validated())]);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function fork(ForkSkillSourceRequest $request, Workspace $workspace, SkillSource $skillSource, SkillForks $forks): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skillSource);
        try {
            $forks->begin($skillSource, $request->user(), $request->validated('credential_id'), $request->validated('fork_repo'));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(['source' => SkillSourceResource::make($skillSource)], 'Preparing your fork.');
    }

    public function completeFork(Workspace $workspace, SkillSource $skillSource, SkillForks $forks): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skillSource);
        try {
            $forks->complete($skillSource);
        } catch (RuntimeException $e) {
            $skillSource->forceFill(['status' => SkillSourceStatus::Failed, 'last_error' => $e->getMessage()])->save();

            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(['source' => SkillSourceResource::make($skillSource)]);
    }

    public function cancelFork(Workspace $workspace, SkillSource $skillSource, SkillForks $forks): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skillSource);
        $forks->cancel($skillSource);

        return ApiResponse::success(['source' => SkillSourceResource::make($skillSource)], 'Original repository connection kept.');
    }

    public function upstream(Workspace $workspace, SkillSource $skillSource, SkillForks $forks): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skillSource);
        try {
            return ApiResponse::success(['upstream' => $forks->upstream($skillSource)]);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function applyUpstream(ApplySkillUpstreamRequest $request, Workspace $workspace, SkillSource $skillSource, SkillForks $forks): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skillSource);
        try {
            $forks->applyUpstream($skillSource, $request->validated('fork_sha'), $request->validated('upstream_sha'));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(['source' => SkillSourceResource::make($skillSource)], 'Original updates merged. Sync to import them.');
    }

    public function update(UpdateSkillSourceRequest $request, Workspace $workspace, SkillSource $skillSource): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skillSource);
        $this->sources->configure($skillSource, $request->user(), $request->validated());

        return ApiResponse::success(['source' => SkillSourceResource::make($skillSource)], 'Sync settings updated.');
    }

    public function export(ExportSkillRequest $request, Workspace $workspace, SkillSource $skillSource): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skillSource);
        $skill = $workspace->skills()->findOrFail($request->validated('skill_id'));
        $this->sources->export($skillSource, $skill, $request->validated('path'));

        return ApiResponse::success(['source' => SkillSourceResource::make($skillSource)], 'Skill linked. It will be exported on the next sync.');
    }

    public function resolve(ResolveSkillSourceRequest $request, Workspace $workspace, SkillSource $skillSource): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skillSource);
        $this->sources->resolve($skillSource, (string) $request->validated('path'), $request->validated('resolution'), $request->validated('commit_sha'), $request->validated('files'));

        return ApiResponse::success(['source' => SkillSourceResource::make($skillSource)], 'Resolution saved. Sync to apply it.');
    }

    public function sync(Workspace $workspace, SkillSource $skillSource): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skillSource);

        $this->sources->queueSync($skillSource, force: true);

        return ApiResponse::success(['source' => SkillSourceResource::make(($skillSource->fresh() ?? $skillSource->forceFill(['status' => SkillSourceStatus::Ready]))->load('credential:id,name'))], 'Syncing.');
    }

    /**
     * `keep_skills` keeps the synced skills as ordinary, editable ones;
     * without it they're removed along with the source.
     */
    public function destroy(Request $request, Workspace $workspace, SkillSource $skillSource): Response
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skillSource);

        $this->sources->disconnect($skillSource, $request->boolean('keep_skills'));

        return ApiResponse::noContent();
    }
}
