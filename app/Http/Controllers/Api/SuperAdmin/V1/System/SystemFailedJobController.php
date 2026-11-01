<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Http\Requests\Api\SuperAdmin\V1\System\FailedJobFilterRequest;
use App\Http\Requests\Api\SuperAdmin\V1\System\FailedJobFilters;
use App\Http\Requests\Api\SuperAdmin\V1\System\FlushFailedJobsRequest;
use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\System\FailedJobService;
use Illuminate\Http\Request;

class SystemFailedJobController extends Controller
{
    public function __construct(private readonly FailedJobService $failedJobs) {}

    public function index(Request $request)
    {
        $filters = $request->validate(FailedJobFilters::rules());

        $page = $this->failedJobs->paginate($filters, min($request->integer('per_page', 25), 100));

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Success',
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function summary(Request $request)
    {
        $request->validate(['hours' => ['nullable', 'integer', 'min:1', 'max:720']]);

        return ApiResponse::success($this->failedJobs->summary($request->integer('hours', 24)));
    }

    public function show(string $id)
    {
        $job = $this->failedJobs->find($id);

        abort_if($job === null, 404, 'No such failed job.');

        return ApiResponse::success($job);
    }

    public function retry(ReasonRequest $request, string $id)
    {
        $this->failedJobs->retry($request->user('super-admin'), $id, $request->validated('reason'));

        return ApiResponse::success(null, 'The job was pushed back onto its queue.');
    }

    public function retryMany(FailedJobFilterRequest $request)
    {
        $result = $this->failedJobs->retryMatching($request->user('super-admin'), $request->filters(), $request->validated('reason'));

        return ApiResponse::success($result, "{$result['retried']} job(s) pushed back onto their queues.");
    }

    public function destroy(StepUpRequest $request, string $id)
    {
        $this->failedJobs->delete($request->user('super-admin'), $id, $request->validated('reason'));

        return ApiResponse::success(null, 'The failed job was deleted.');
    }

    public function flush(FlushFailedJobsRequest $request)
    {
        $deleted = $this->failedJobs->flush($request->user('super-admin'), $request->filters(), $request->validated('reason'));

        return ApiResponse::success(['deleted' => $deleted], "{$deleted} failed job(s) deleted.");
    }
}
