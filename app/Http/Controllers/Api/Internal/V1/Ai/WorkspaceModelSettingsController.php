<?php

namespace App\Http\Controllers\Api\Internal\V1\Ai;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Ai\UpdateWorkspaceModelSettingsRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\WorkspaceModelSettings;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;

/**
 * The workspace's default model and allowed-model list: readable by anyone
 * who can see agents (the model picker shows both), changeable only by an
 * admin.
 */
class WorkspaceModelSettingsController extends Controller
{
    public function show(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        return ApiResponse::success(['settings' => $this->present($request, WorkspaceModelSettings::forWorkspace($workspace->id))]);
    }

    public function update(UpdateWorkspaceModelSettingsRequest $request, Workspace $workspace)
    {
        $settings = WorkspaceModelSettings::query()->updateOrCreate(['workspace_id' => $workspace->id], $request->validated());

        return ApiResponse::success(['settings' => $this->present($request, $settings)], 'Model settings updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Request $request, WorkspaceModelSettings $settings): array
    {
        return [
            'default_model_catalog_id' => $settings->default_model_catalog_id,
            'allowed_model_catalog_ids' => $settings->allowed_model_catalog_ids,
            'can_manage' => $request->user()->can(Permission::WorkspaceUpdate->value),
        ];
    }
}
