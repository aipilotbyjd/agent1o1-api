<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workflows\PublishRequestStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\ReviewPublishRequestRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\StorePublishRequestRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowPublishRequestResource;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPublishRequest;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\PublishRequests;
use Illuminate\Http\Request;

/**
 * Review before publishing: see `PublishRequests`. The list carries the
 * open request in full (with whether the draft or the live version has moved
 * since), and the rest as history without their graphs.
 */
class WorkflowPublishRequestController extends Controller
{
    private const int HISTORY_LIMIT = 50;

    public function __construct(private readonly PublishRequests $publishRequests) {}

    public function index(Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowView);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $pending = $workflow->publishRequests()
            ->where('status', PublishRequestStatus::Pending)
            ->with(['requester:id,name', 'baseVersion:id,version'])
            ->latest()
            ->first()
            ?->setRelation('workflow', $workflow);

        $history = $workflow->publishRequests()
            ->select(['id', 'workflow_id', 'workspace_id', 'requested_by', 'reviewed_by', 'status', 'notes', 'review_notes', 'reviewed_at', 'base_version_id', 'published_version_id', 'created_at'])
            ->with(['requester:id,name', 'reviewer:id,name', 'baseVersion:id,version', 'publishedVersion:id,version'])
            ->latest()
            ->limit(self::HISTORY_LIMIT)
            ->get();

        return ApiResponse::success([
            'pending' => $pending === null ? null : WorkflowPublishRequestResource::make($pending),
            'requests' => WorkflowPublishRequestResource::collection($history),
        ]);
    }

    public function show(Workspace $workspace, Workflow $workflow, WorkflowPublishRequest $publishRequest)
    {
        $this->requirePermission(Permission::WorkflowView);
        $this->ensureRequestBelongs($workspace, $workflow, $publishRequest);

        return ApiResponse::success([
            'request' => WorkflowPublishRequestResource::make($this->withDetails($publishRequest, $workflow)),
        ]);
    }

    public function store(StorePublishRequestRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowPublish);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $publishRequest = $this->publishRequests->request($workflow, $request->user(), $request->validated('notes'));

        return ApiResponse::created([
            'request' => WorkflowPublishRequestResource::make($this->withDetails($publishRequest, $workflow)),
        ], 'Review requested. Nothing goes live until it is approved.');
    }

    public function approve(ReviewPublishRequestRequest $request, Workspace $workspace, Workflow $workflow, WorkflowPublishRequest $publishRequest)
    {
        $this->requirePermission(Permission::WorkflowApprovePublish);
        $this->ensureRequestBelongs($workspace, $workflow, $publishRequest);

        $publishRequest = $this->publishRequests->approve($publishRequest, $request->user(), $request->validated('notes'));
        $version = $publishRequest->publishedVersion;

        return ApiResponse::success([
            'request' => WorkflowPublishRequestResource::make($this->withDetails($publishRequest, $workflow)),
            'workflow' => WorkflowResource::make($workflow->fresh()),
        ], "Approved. v{$version->version} is live.");
    }

    public function reject(ReviewPublishRequestRequest $request, Workspace $workspace, Workflow $workflow, WorkflowPublishRequest $publishRequest)
    {
        $this->requirePermission(Permission::WorkflowApprovePublish);
        $this->ensureRequestBelongs($workspace, $workflow, $publishRequest);

        $publishRequest = $this->publishRequests->reject($publishRequest, $request->user(), $request->validated('notes'));

        return ApiResponse::success([
            'request' => WorkflowPublishRequestResource::make($this->withDetails($publishRequest, $workflow)),
        ], 'Rejected. The requester has been told.');
    }

    public function withdraw(Request $request, Workspace $workspace, Workflow $workflow, WorkflowPublishRequest $publishRequest)
    {
        $this->requirePermission(Permission::WorkflowView);
        $this->ensureRequestBelongs($workspace, $workflow, $publishRequest);

        $publishRequest = $this->publishRequests->withdraw($publishRequest, $request->user());

        return ApiResponse::success([
            'request' => WorkflowPublishRequestResource::make($this->withDetails($publishRequest, $workflow)),
        ], 'Review request withdrawn.');
    }

    private function ensureRequestBelongs(Workspace $workspace, Workflow $workflow, WorkflowPublishRequest $publishRequest): void
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($publishRequest->workflow_id !== $workflow->id, 404);
    }

    private function withDetails(WorkflowPublishRequest $publishRequest, Workflow $workflow): WorkflowPublishRequest
    {
        return $publishRequest
            ->load(['requester:id,name', 'reviewer:id,name', 'baseVersion:id,version', 'publishedVersion:id,version'])
            ->setRelation('workflow', $workflow->fresh());
    }
}
