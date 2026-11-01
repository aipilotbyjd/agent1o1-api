<?php

namespace App\Http\Controllers\Api\Public\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Public\V1\PublishWorkflowRequest;
use App\Http\Requests\Api\Public\V1\ReplaceGraphRequest;
use App\Http\Resources\Api\Public\V1\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowVersion;
use App\Services\Workflows\GraphValidator;
use Illuminate\Http\Request;

/**
 * Programmatic authoring: replace a workflow's draft graph, read it back,
 * and publish it as a version.
 *
 * The two-step draft-then-publish split is deliberate and mirrors the
 * editor's: `replace()` accepts a mid-edit graph (only per-node config
 * schemas are checked), while `publish()` runs the full `GraphValidator`
 * sequence and is what makes the workflow runnable. Both surface failures as
 * 422s via `WorkflowValidationException`.
 */
class WorkflowGraphController extends Controller
{
    public function show(Request $request, Workflow $workflow)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $workflow);

        return ApiResponse::success([
            'graph' => $workflow->draftGraph(),
            'has_unpublished_changes' => $workflow->has_unpublished_changes,
        ]);
    }

    public function replace(ReplaceGraphRequest $request, Workflow $workflow)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $workflow);

        $workflow->replaceGraph($request->validated());

        return ApiResponse::success([
            'graph' => $workflow->fresh()->draftGraph(),
        ], 'Graph saved.');
    }

    /**
     * Publishing is what pins future runs: see `StartWorkflowRunAction`.
     */
    public function publish(PublishWorkflowRequest $request, Workflow $workflow)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $workflow);

        $version = $workflow->publishVersion($request->validated('notes'));

        return ApiResponse::created([
            'workflow' => WorkflowResource::make($workflow->fresh()),
            'version' => $this->versionPayload($version),
        ], 'Workflow published.');
    }

    /**
     * A dry validation of the draft, for a caller that wants to know whether
     * `publish()` would succeed before committing to it. Answers 200 with the
     * findings: an invalid graph is the expected result of asking.
     */
    public function validateGraph(Request $request, Workflow $workflow, GraphValidator $validator)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $workflow);

        $graph = $workflow->draftGraph();
        $issues = $validator->validate($graph['nodes'], $graph['edges'], $workflow->workspace_id);

        return ApiResponse::success(['valid' => $issues === [], 'issues' => $issues]);
    }

    /**
     * @return array<string, mixed>
     */
    private function versionPayload(WorkflowVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'notes' => $version->notes,
            'created_at' => $version->created_at,
        ];
    }
}
