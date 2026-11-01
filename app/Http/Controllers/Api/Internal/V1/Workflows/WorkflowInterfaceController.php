<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\SubmitWorkflowInterfaceRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\UpdateWorkflowInterfaceRequest;
use App\Http\Resources\Api\Internal\V1\Runs\RunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\WorkflowInterface;

/**
 * A workflow's front door: the fields a run expects, and a way to submit
 * them. This is what makes a workflow usable by someone who did not build it:
 * without an interface, "run this workflow" means "post the right arbitrary
 * JSON", which only its author knows how to do.
 *
 * The contract is either declared here or derived from the graph; see
 * `WorkflowInterface`.
 */
class WorkflowInterfaceController extends Controller
{
    public function __construct(
        private readonly WorkflowInterface $interface,
        private readonly StartWorkflowRunAction $startWorkflowRun,
    ) {}

    public function show(Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowView);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        return ApiResponse::success([
            'interface' => [
                ...$this->interface->describe($workflow),
                'outputs' => $this->interface->outputs($workflow),
                'workflow_id' => $workflow->id,
                'published' => $workflow->isPublished(),
            ],
        ]);
    }

    public function update(UpdateWorkflowInterfaceRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $fields = $request->validated('fields');

        // An empty declaration is stored as null, not as `{fields: []}`;
        // "no declaration" and "declared to have no fields" would otherwise
        // be indistinguishable, and only the first should fall back to
        // deriving the interface from the graph.
        $workflow->update(['input_schema' => $fields === [] ? null : ['fields' => $fields]]);

        return ApiResponse::success([
            'interface' => $this->interface->describe($workflow->fresh()),
        ], 'Interface updated successfully.');
    }

    /**
     * Starts a run from a filled-in form. Validation happens in
     * `SubmitWorkflowInterfaceRequest` against the same contract `show()`
     * returns, so a form built from that response can't submit something the
     * workflow will reject.
     */
    public function submit(SubmitWorkflowInterfaceRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $run = $this->startWorkflowRun->execute(
            $workflow,
            $this->interface->applyDefaults($workflow, $request->validated('input') ?? []),
            $request->user(),
        );

        return ApiResponse::success(['run' => RunResource::make($run)], 'Run started.', 202);
    }
}
