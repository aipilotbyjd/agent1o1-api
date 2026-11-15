<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Operations;

use App\Services\SuperAdmin\Operations\TaskState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One checklist line as the panel shows it: the task and where it stands.
 *
 * @property TaskState $resource
 */
class TaskStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->resource;
        $task = $state->task;

        return [
            ...(new OperationsTaskResource($task))->resolve($request),
            'status' => $state->status->value,
            'detail' => $state->detail,
            'period' => $state->period,
            'due_at' => $state->dueAt?->toIso8601String(),
            'done' => $state->done === null ? null : [
                'at' => $state->done->created_at,
                'by' => $state->done->actor?->name,
                'note' => $state->done->note,
            ],
            'snoozed' => $state->snoozed === null ? null : [
                'until' => $state->snoozed->snoozed_until,
                'by' => $state->snoozed->actor?->name,
                'reason' => $state->snoozed->note,
            ],
            'last_done_at' => $state->lastDoneAt?->toIso8601String(),
        ];
    }
}
