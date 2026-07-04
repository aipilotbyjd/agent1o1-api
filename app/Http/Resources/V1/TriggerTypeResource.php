<?php

namespace App\Http\Resources\V1;

use App\Models\TriggerType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TriggerType
 */
class TriggerTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'execution_mode' => $this->execution_mode,
            'zapier_mode' => $this->zapier_mode,
            'requires_credential' => $this->requires_credential,
            'requires_config_fields' => $this->requires_config_fields,
            'webhook_events' => $this->webhook_events,
            'fields' => TriggerTypeFieldResource::collection($this->whenLoaded('fields')),
        ];
    }
}
