<?php

namespace App\Http\Resources\V1;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invitation
 */
class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'inviter' => new UserResource($this->whenLoaded('inviter')),
            'expires_at' => $this->expires_at,
            'status' => $this->status(),
            'created_at' => $this->created_at,
        ];
    }
}
