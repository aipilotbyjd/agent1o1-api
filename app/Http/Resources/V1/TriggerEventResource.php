<?php

namespace App\Http\Resources\V1;

use App\Models\TriggerEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TriggerEvent
 */
class TriggerEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trigger_id' => $this->trigger_id,
            'workflow_id' => $this->workflow_id,
            'event_data' => $this->event_data,
            'status' => $this->status,
            'processed_at' => $this->processed_at,
            'attempts' => $this->attempts,
            'created_at' => $this->created_at,
        ];
    }
}
