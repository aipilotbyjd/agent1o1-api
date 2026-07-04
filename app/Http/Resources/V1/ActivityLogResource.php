<?php

namespace App\Http\Resources\V1;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityLog
 */
class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'description' => $this->description,
            'properties' => $this->properties,
            'user' => new UserResource($this->whenLoaded('user')),
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
        ];
    }
}
