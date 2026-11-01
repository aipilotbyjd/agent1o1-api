<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateReflectionSettingsRequest;
use App\Http\Resources\Api\Internal\V1\Agents\ReflectionSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\ReflectionSettings;
use App\Models\Workspaces\Workspace;

class ReflectionSettingsController extends Controller
{
    public function show(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $settings = $agent->reflectionSettings ?? $agent->reflectionSettings()->save(new ReflectionSettings);

        return ApiResponse::success(['settings' => ReflectionSettingsResource::make($settings)]);
    }

    public function update(UpdateReflectionSettingsRequest $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $settings = $agent->reflectionSettings ?? $agent->reflectionSettings()->save(new ReflectionSettings);
        $settings->update($request->validated());

        return ApiResponse::success(
            ['settings' => ReflectionSettingsResource::make($settings->fresh())],
            'Reflection settings updated successfully.',
        );
    }
}
