<?php

namespace App\Http\Controllers\Api\Public\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\WorkflowInterface;
use Illuminate\Http\Request;

/**
 * The input contract a caller must satisfy to start this workflow: the same
 * description the editor shows, so an external form or a generated client can
 * be built from it without anyone hand-documenting the payload.
 *
 * Read-only here: declaring an interface is authoring, which belongs to the
 * internal API.
 */
class WorkflowInterfaceController extends Controller
{
    public function show(Request $request, Workflow $workflow, WorkflowInterface $interface)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $workflow);

        return ApiResponse::success([
            'interface' => [
                ...$interface->describe($workflow),
                'outputs' => $interface->outputs($workflow),
                'workflow_id' => $workflow->id,
                'published' => $workflow->isPublished(),
            ],
        ]);
    }
}
