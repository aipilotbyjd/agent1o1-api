<?php

namespace App\Http\Controllers\Api\Internal\V1\Secrets;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Secrets\StoreSecretRequest;
use App\Http\Requests\Api\Internal\V1\Secrets\UpdateSecretRequest;
use App\Http\Resources\Api\Internal\V1\Secrets\SecretResource;
use App\Http\Responses\ApiResponse;
use App\Models\Secrets\Secret;
use App\Models\Workspaces\Workspace;

class SecretController extends Controller
{
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::SecretView);

        $secrets = $workspace->secrets()->orderBy('key')->get();

        return ApiResponse::success(['secrets' => SecretResource::collection($secrets)]);
    }

    public function show(Workspace $workspace, Secret $secret)
    {
        $this->requirePermission(Permission::SecretView);
        $this->ensureBelongsToWorkspace($workspace, $secret);

        return ApiResponse::success(['secret' => SecretResource::make($secret)]);
    }

    public function store(StoreSecretRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::SecretManage);

        $secret = $workspace->secrets()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['secret' => SecretResource::make($secret)], 'Secret created.');
    }

    public function update(UpdateSecretRequest $request, Workspace $workspace, Secret $secret)
    {
        $this->requirePermission(Permission::SecretManage);
        $this->ensureBelongsToWorkspace($workspace, $secret);

        $secret->update($request->validated());

        return ApiResponse::success(['secret' => SecretResource::make($secret)], 'Secret updated.');
    }

    public function destroy(Workspace $workspace, Secret $secret)
    {
        $this->requirePermission(Permission::SecretManage);
        $this->ensureBelongsToWorkspace($workspace, $secret);

        $secret->delete();

        return ApiResponse::noContent();
    }
}
