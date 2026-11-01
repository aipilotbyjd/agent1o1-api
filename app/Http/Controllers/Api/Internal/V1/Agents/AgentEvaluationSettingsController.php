<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateAgentEvaluationSettingsRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentEvaluationSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvaluationSettings;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Str;

class AgentEvaluationSettingsController extends Controller
{
    public function show(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $settings = $agent->evaluationSettings ?? $agent->evaluationSettings()->save(new AgentEvaluationSettings);

        return ApiResponse::success(['settings' => AgentEvaluationSettingsResource::make($settings)]);
    }

    public function update(UpdateAgentEvaluationSettingsRequest $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $settings = $agent->evaluationSettings ?? $agent->evaluationSettings()->save(new AgentEvaluationSettings);

        $data = $request->validated();

        if (array_key_exists('criteria', $data)) {
            $data['criteria'] = $this->withIds($data['criteria']);
        }

        if (array_key_exists('data_points', $data)) {
            $data['data_points'] = $this->withIds($data['data_points']);
        }

        $settings->update($data);

        return ApiResponse::success(
            ['settings' => AgentEvaluationSettingsResource::make($settings->fresh())],
            'Evaluation settings updated successfully.',
        );
    }

    /**
     * Assigns a stable id to every entry that doesn't already have one, so
     * the judge's `criteria_results`/`data_results` can reference a
     * criterion or data point by id across edits instead of by its
     * (editable) name.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function withIds(array $entries): array
    {
        return array_map(
            fn (array $entry): array => $entry + ['id' => (string) Str::uuid()],
            $entries,
        );
    }
}
