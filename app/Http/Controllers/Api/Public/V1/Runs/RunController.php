<?php

namespace App\Http\Controllers\Api\Public\V1\Runs;

use App\Actions\Workflows\CancelRunAction;
use App\Actions\Workflows\RetryRunAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Public\V1\IndexRunsRequest;
use App\Http\Requests\Api\Public\V1\RetryRunRequest;
use App\Http\Requests\Api\Public\V1\StartRunRequest;
use App\Http\Resources\Api\Public\V1\RunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use Illuminate\Http\Request;

class RunController extends Controller
{
    public function __construct(
        private readonly StartWorkflowRunAction $startWorkflowRun,
        private readonly CancelRunAction $cancelRun,
        private readonly RetryRunAction $retryRun,
    ) {}

    public function index(IndexRunsRequest $request)
    {
        $runs = $this->apiKeyWorkspace($request)->runs()
            ->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->validated('workflow_id'), fn ($query, $workflowId) => $query->where('workflow_id', $workflowId))
            ->with('nodeRuns.creditTransaction')
            ->latest()
            ->paginate($request->validated('per_page') ?? 25)
            ->withQueryString();

        return ApiResponse::paginated(RunResource::collection($runs));
    }

    public function store(StartRunRequest $request, Workflow $workflow)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $workflow);

        $run = $this->startWorkflowRun->execute($workflow, $request->validated('input') ?? [], triggerType: 'api');

        return ApiResponse::success(['run' => RunResource::make($run)], 'Run started.', 202);
    }

    public function show(Request $request, Run $run)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $run);

        return ApiResponse::success(['run' => RunResource::make($run->load('nodeRuns.creditTransaction'))]);
    }

    /**
     * Cancels the whole run tree: see `CancelRunAction`. 409 if the run has
     * already finished.
     */
    public function cancel(Request $request, Run $run)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $run);

        $cancelled = $this->cancelRun->execute($run);

        return ApiResponse::success(['run' => RunResource::make($cancelled->load('nodeRuns.creditTransaction'))], 'Run cancelled.');
    }

    public function retry(RetryRunRequest $request, Run $run)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $run);

        $retry = $this->retryRun->execute($run, fromNodeKey: $request->validated('from_node_key'));

        return ApiResponse::success(['run' => RunResource::make($retry->load('nodeRuns.creditTransaction'))], 'Run retried.', 202);
    }
}
