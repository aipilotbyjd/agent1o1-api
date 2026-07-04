<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TriggerEventResource;
use App\Jobs\ProcessTriggerEventJob;
use App\Models\Trigger;
use App\Models\TriggerEvent;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TriggerEventController extends Controller
{
    public function index(Request $request, Workspace $workspace, Trigger $trigger): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionView)) {
            return $forbidden;
        }

        $events = $trigger->events()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->paginate((int) $request->query('per_page', 20));

        return $this->paginatedResponse(
            'Trigger events retrieved.',
            TriggerEventResource::collection($events),
        );
    }

    public function replay(Request $request, Workspace $workspace, Trigger $trigger, TriggerEvent $event): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowExecute)) {
            return $forbidden;
        }

        $replay = TriggerEvent::create([
            'trigger_id' => $trigger->id,
            'workflow_id' => $event->workflow_id,
            'workspace_id' => $event->workspace_id,
            'event_data' => $event->event_data,
            'status' => 'pending',
        ]);

        ProcessTriggerEventJob::dispatch($replay->id)->onQueue('triggers');

        return $this->successResponse('Event replay queued.', new TriggerEventResource($replay), 202);
    }
}
