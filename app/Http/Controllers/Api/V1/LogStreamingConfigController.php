<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LogStreaming\StoreLogStreamingConfigRequest;
use App\Http\Requests\Api\V1\LogStreaming\UpdateLogStreamingConfigRequest;
use App\Http\Resources\V1\LogStreamingConfigResource;
use App\Models\LogStreamingConfig;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogStreamingConfigController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        return $this->successResponse(
            'Log streaming configs retrieved.',
            LogStreamingConfigResource::collection($workspace->logStreamingConfigs()->latest()->get()),
        );
    }

    public function store(StoreLogStreamingConfigRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $config = $workspace->logStreamingConfigs()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'is_active' => $request->validated('is_active') ?? true,
        ]);

        return $this->successResponse('Log streaming config created.', new LogStreamingConfigResource($config), 201);
    }

    public function update(UpdateLogStreamingConfigRequest $request, Workspace $workspace, LogStreamingConfig $logStreamingConfig): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $logStreamingConfig->update($request->validated());

        return $this->successResponse('Log streaming config updated.', new LogStreamingConfigResource($logStreamingConfig));
    }

    public function destroy(Request $request, Workspace $workspace, LogStreamingConfig $logStreamingConfig): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $logStreamingConfig->delete();

        return $this->successResponse('Log streaming config deleted.');
    }
}
