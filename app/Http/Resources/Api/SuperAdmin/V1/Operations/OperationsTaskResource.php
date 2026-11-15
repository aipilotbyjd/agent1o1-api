<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Operations;

use App\Models\Operations\OperationsTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OperationsTask
 */
class OperationsTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $this->title,
            'description' => $this->description,
            'cadence' => $this->cadence->value,
            'kind' => $this->kind->value,
            'target' => $this->target,
            'due_after_hours' => $this->due_after_hours,
            'sort_order' => $this->sort_order,
            'enabled' => $this->enabled,
            'is_system' => $this->is_system,
            'assigned_to' => $this->whenLoaded('assignee', fn (): ?array => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
