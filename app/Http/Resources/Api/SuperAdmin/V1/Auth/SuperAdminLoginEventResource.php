<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Auth;

use App\Models\SuperAdmin\SuperAdminLoginEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SuperAdminLoginEvent
 */
class SuperAdminLoginEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'context' => $this->context,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at,
        ];
    }
}
