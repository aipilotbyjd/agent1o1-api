<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AiFixSuggestionResource;
use App\Jobs\DiagnoseFailedNode;
use App\Models\AiFixSuggestion;
use App\Models\Execution;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAutofixController extends Controller
{
    public function index(Request $request, Workspace $workspace, Execution $execution): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionView)) {
            return $denied;
        }

        return $this->successResponse(
            'Fix suggestions retrieved.',
            AiFixSuggestionResource::collection($execution->fixSuggestions()->latest()->get()),
        );
    }

    public function diagnose(Request $request, Workspace $workspace, Execution $execution): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionManage)) {
            return $denied;
        }

        $validated = $request->validate([
            'node_id' => ['required', 'string'],
        ]);

        DiagnoseFailedNode::dispatch($execution->id, $validated['node_id']);

        return $this->successResponse('Diagnosis queued. Suggestions will appear shortly.', null, 202);
    }

    public function apply(Request $request, Workspace $workspace, Execution $execution, AiFixSuggestion $fixSuggestion): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $denied;
        }

        $index = (int) $request->validate([
            'suggestion_index' => ['required', 'integer', 'min:0'],
        ])['suggestion_index'];

        $fixConfig = $fixSuggestion->suggestions[$index]['fix_config'] ?? null;

        if (! is_array($fixConfig) || $fixConfig === []) {
            return $this->errorResponse('Selected suggestion has no applicable fix.', 422);
        }

        $version = $execution->workflow?->currentVersion;

        if (! $version) {
            return $this->errorResponse('Workflow has no current version to patch.', 422);
        }

        $nodes = collect($version->nodes_data ?? [])->map(function ($node) use ($fixSuggestion, $fixConfig) {
            if (($node['id'] ?? null) === $fixSuggestion->node_id) {
                $node['config'] = array_merge($node['config'] ?? [], $fixConfig);
            }

            return $node;
        })->all();

        $version->update(['nodes_data' => $nodes]);
        $fixSuggestion->update(['status' => 'applied']);

        return $this->successResponse('Fix applied to the current workflow version.', new AiFixSuggestionResource($fixSuggestion));
    }

    public function dismiss(Request $request, Workspace $workspace, Execution $execution, AiFixSuggestion $fixSuggestion): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionManage)) {
            return $denied;
        }

        $fixSuggestion->update(['status' => 'dismissed']);

        return $this->successResponse('Suggestion dismissed.');
    }
}
