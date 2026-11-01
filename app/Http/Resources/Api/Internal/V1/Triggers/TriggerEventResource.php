<?php

namespace App\Http\Resources\Api\Internal\V1\Triggers;

use App\Models\Triggers\TriggerEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TriggerEvent
 */
class TriggerEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trigger_id' => $this->trigger_id,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'run_id' => $this->run_id,
            'payload' => $this->payload,
            'error' => $this->error,
            'delivery_id' => $this->delivery_id,
            'attempts' => $this->attempts,
            'duplicate_count' => $this->duplicate_count,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
        ];
    }
}
