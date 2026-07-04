<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Trigger\SetPollingIntervalRequest;
use App\Http\Requests\Api\V1\Trigger\SetScheduleRequest;
use App\Http\Requests\Api\V1\Trigger\StoreTriggerRequest;
use App\Http\Resources\V1\TriggerResource;
use App\Models\Trigger;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\TriggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TriggerController extends Controller
{
    public function __construct(private readonly TriggerService $triggerService) {}

    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowView)) {
            return $forbidden;
        }

        return $this->successResponse(
            'Triggers retrieved.',
            TriggerResource::collection($workflow->triggers),
        );
    }

    public function store(StoreTriggerRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        try {
            $trigger = $this->triggerService->create($workflow, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse('Trigger created.', new TriggerResource($trigger), 201);
    }

    public function show(Request $request, Workspace $workspace, Workflow $workflow, Trigger $trigger): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowView)) {
            return $forbidden;
        }

        return $this->successResponse('Trigger retrieved.', new TriggerResource($trigger));
    }

    public function destroy(Request $request, Workspace $workspace, Workflow $workflow, Trigger $trigger): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $forbidden;
        }

        $this->triggerService->delete($trigger);

        return $this->successResponse('Trigger deleted.');
    }

    public function pause(Request $request, Workspace $workspace, Workflow $workflow, Trigger $trigger): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $forbidden;
        }

        $trigger = $this->triggerService->pause($trigger);

        return $this->successResponse('Trigger paused.', new TriggerResource($trigger));
    }

    public function resume(Request $request, Workspace $workspace, Workflow $workflow, Trigger $trigger): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $forbidden;
        }

        $trigger = $this->triggerService->resume($trigger);

        return $this->successResponse('Trigger resumed.', new TriggerResource($trigger));
    }

    public function setPollingInterval(SetPollingIntervalRequest $request, Workspace $workspace, Workflow $workflow, Trigger $trigger): JsonResponse
    {
        $trigger = $this->triggerService->setPollingInterval(
            $trigger,
            $request->validated('polling_interval_seconds'),
        );

        return $this->successResponse('Polling interval updated.', new TriggerResource($trigger));
    }

    public function setSchedule(SetScheduleRequest $request, Workspace $workspace, Workflow $workflow, Trigger $trigger): JsonResponse
    {
        try {
            $trigger = $this->triggerService->setSchedule(
                $trigger,
                $request->validated('schedule_expression'),
                $request->validated('schedule_timezone') ?? 'UTC',
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse('Schedule updated.', new TriggerResource($trigger));
    }
}
