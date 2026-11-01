<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\ReflectionRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReflectionRun
 */
class ReflectionRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'status' => $this->status->value,
            'sessions_analyzed_count' => $this->sessions_analyzed_count,
            'skip_reason' => $this->skip_reason,
            'reflections_count' => $this->whenCounted('reflections'),
            'reflections' => ReflectionResource::collection($this->whenLoaded('reflections')),
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
        ];
    }
}
