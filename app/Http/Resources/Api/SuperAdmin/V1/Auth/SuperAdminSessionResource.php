<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Auth;

use App\Models\SuperAdmin\SuperAdminToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One live session. Token hashes are never exposed.
 *
 * @mixin SuperAdminToken
 */
class SuperAdminSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $current = $request->attributes->get('super_admin_token');

        return [
            'id' => $this->id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
            'expires_at' => $this->absolute_expires_at,
            'current' => $current instanceof SuperAdminToken && $current->family_id === $this->family_id,
        ];
    }
}
