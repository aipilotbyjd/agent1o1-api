<?php

namespace App\Http\Resources\Api\Internal\V1\Auth;

use App\Models\Auth\AuthEventLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuthEventLog
 */
class AuthEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event->value,
            'label' => $this->event->label(),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'context' => $this->context,
            'created_at' => $this->created_at,
        ];
    }
}
