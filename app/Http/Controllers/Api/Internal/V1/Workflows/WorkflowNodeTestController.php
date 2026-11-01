<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\TestWorkflowNodeRequest;
use App\Http\Resources\Api\Internal\V1\Runs\NodeRunDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNode;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\NodeTester;

/**
 * Executes one draft node for real: see `NodeTester` for what that does and
 * doesn't include. Requires `WorkflowManage` *and* `RunTrigger`: editing
 * rights alone shouldn't let someone fire a node's real side effects, and
 * trigger rights alone shouldn't reach into a draft.
 */
class WorkflowNodeTestController extends Controller
{
    public function __construct(private readonly NodeTester $tester) {}

    public function store(TestWorkflowNodeRequest $request, Workspace $workspace, Workflow $workflow, WorkflowNode $node)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($node->workflow_id !== $workflow->id, 404);

        $nodeRun = $this->tester->test(
            $workflow,
            $node,
            $request->validated('input') ?? [],
            $request->validated('nodes') ?? [],
            $request->user(),
            $request->validated('config'),
        );

        return ApiResponse::success(['node_run' => NodeRunDetailResource::make($nodeRun)], 'Node executed.');
    }
}
