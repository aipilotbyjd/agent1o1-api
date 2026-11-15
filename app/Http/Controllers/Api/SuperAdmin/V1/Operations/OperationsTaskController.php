<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Operations\CompleteTaskRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Operations\SnoozeTaskRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Operations\TaskRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Operations\OperationsTaskEntryResource;
use App\Http\Resources\Api\SuperAdmin\V1\Operations\OperationsTaskResource;
use App\Http\Responses\ApiResponse;
use App\Models\Operations\OperationsTask;
use App\Services\SuperAdmin\Operations\TaskActions;
use Illuminate\Http\Request;

/**
 * Ticking, snoozing and editing checklist tasks. Ticking and snoozing need
 * `operations.manage`; adding, editing and removing tasks needs
 * `operations.configure`.
 */
class OperationsTaskController extends Controller
{
    public function __construct(private readonly TaskActions $actions) {}

    public function index(Request $request)
    {
        $tasks = OperationsTask::query()
            ->with('assignee:id,name')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return ApiResponse::success(OperationsTaskResource::collection($tasks)->resolve($request));
    }

    public function store(TaskRequest $request)
    {
        $task = $this->actions->create($request->user('super-admin'), $request->validated());

        return ApiResponse::created((new OperationsTaskResource($task->load('assignee:id,name')))->resolve($request), 'Task added.');
    }

    public function update(TaskRequest $request, OperationsTask $task)
    {
        $task = $this->actions->update($request->user('super-admin'), $task, $request->validated());

        return ApiResponse::success((new OperationsTaskResource($task->load('assignee:id,name')))->resolve($request), 'Task updated.');
    }

    public function destroy(Request $request, OperationsTask $task)
    {
        $this->actions->delete($request->user('super-admin'), $task);

        return ApiResponse::success(null, 'Task deleted.');
    }

    public function complete(CompleteTaskRequest $request, OperationsTask $task)
    {
        $entry = $this->actions->complete($request->user('super-admin'), $task, $request->validated('note'));

        return ApiResponse::success((new OperationsTaskEntryResource($entry->load('actor:id,name')))->resolve($request), 'Marked done.');
    }

    public function reopen(Request $request, OperationsTask $task)
    {
        $this->actions->reopen($request->user('super-admin'), $task);

        return ApiResponse::success(null, 'Marked as not done.');
    }

    public function snooze(SnoozeTaskRequest $request, OperationsTask $task)
    {
        $entry = $this->actions->snooze($request->user('super-admin'), $task, (int) $request->validated('hours'), $request->validated('reason'));

        return ApiResponse::success((new OperationsTaskEntryResource($entry->load('actor:id,name')))->resolve($request), 'Snoozed.');
    }
}
