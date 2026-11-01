<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\SubagentTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubagentTask
 */
class SubagentTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent' => [
                'id' => $this->agent?->id,
                'name' => $this->agent?->name,
                'icon' => $this->agent?->icon,
                'color' => $this->agent?->color,
            ],
            'session_id' => $this->session_id,
            'task' => $this->task,
            'status' => $this->status->value,
            'result' => $this->result,
            'error' => $this->error,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
        ];
    }
}
