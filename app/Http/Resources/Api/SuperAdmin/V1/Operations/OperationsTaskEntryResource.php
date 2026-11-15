<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Operations;

use App\Models\Operations\OperationsTaskEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OperationsTaskEntry
 */
class OperationsTaskEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'period' => $this->period,
            'note' => $this->note,
            'snoozed_until' => $this->snoozed_until,
            'created_at' => $this->created_at,
            'task' => $this->whenLoaded('task', fn (): array => ['id' => $this->task->id, 'title' => $this->task->title, 'cadence' => $this->task->cadence->value]),
            'actor' => $this->whenLoaded('actor', fn (): ?array => $this->actor === null ? null : ['id' => $this->actor->id, 'name' => $this->actor->name]),
        ];
    }
}
