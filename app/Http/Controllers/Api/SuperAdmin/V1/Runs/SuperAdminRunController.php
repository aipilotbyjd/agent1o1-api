<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Runs;

use App\Enums\RunStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Runs\SuperAdminRunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\SuperAdmin\Runs\SuperAdminRunService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SuperAdminRunController extends Controller
{
    public function __construct(
        private readonly SuperAdminRunService $runs,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(RunStatus::class)],
            'workspace_id' => ['nullable', 'uuid'],
            'user_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $runs = Run::query()
            ->with(['workspace:id,name', 'workflow:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('workspace_id'), fn ($query) => $query->where('workspace_id', $request->string('workspace_id')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('triggered_by', $request->string('user_id')))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', $request->date('to')))
            ->when($request->boolean('with_error'), fn ($query) => $query->whereNotNull('error'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(min($request->integer('per_page', 50), 100));

        return ApiResponse::cursorPaginated(SuperAdminRunResource::collection($runs));
    }

    public function show(Request $request, Run $run)
    {
        $run->load(['workspace:id,name', 'workflow:id,name']);

        $this->audit->record($request->user('super-admin'), 'runs.viewed', $run);

        return ApiResponse::success((new SuperAdminRunResource($run))->resolve($request));
    }

    /**
     * The steps of one run, without their inputs and outputs.
     */
    public function nodeRuns(Request $request, Run $run)
    {
        $nodeRuns = $run->nodeRuns()->orderBy('created_at')->get();

        return ApiResponse::success($nodeRuns->map(fn (NodeRun $nodeRun): array => [
            'id' => $nodeRun->id,
            'key' => $nodeRun->key,
            'type' => $nodeRun->type,
            'status' => $nodeRun->status->value,
            'error' => $nodeRun->error,
            'attempt' => $nodeRun->attempt,
            'started_at' => $nodeRun->started_at,
            'finished_at' => $nodeRun->finished_at,
        ])->all());
    }

    public function retry(ReasonRequest $request, Run $run)
    {
        $retried = $this->runs->retry($request->user('super-admin'), $run, $request->validated('reason'));

        return ApiResponse::created((new SuperAdminRunResource($retried))->resolve($request), 'Run restarted as a new run.');
    }

    public function cancel(ReasonRequest $request, Run $run)
    {
        $cancelled = $this->runs->cancel($request->user('super-admin'), $run, $request->validated('reason'));

        return ApiResponse::success((new SuperAdminRunResource($cancelled))->resolve($request), 'Run cancelled.');
    }

    /**
     * The most common failure messages over the last `days` (default 7).
     */
    public function failureSummary(Request $request)
    {
        $days = max(1, min($request->integer('days', 7), 90));

        $rows = Run::query()
            ->where('status', RunStatus::Failed->value)
            ->whereNotNull('error')
            ->where('created_at', '>=', now()->subDays($days))
            ->select(DB::raw('substr(error, 1, 160) as message'), DB::raw('count(*) as total'), DB::raw('count(distinct workspace_id) as workspaces'))
            ->groupBy(DB::raw('substr(error, 1, 160)'))
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        return ApiResponse::success([
            'days' => $days,
            'errors' => $rows->map(fn ($row): array => [
                'message' => $row->message,
                'total' => (int) $row->total,
                'workspaces' => (int) $row->workspaces,
            ])->all(),
        ]);
    }
}
