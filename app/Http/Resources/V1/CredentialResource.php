<?php

namespace App\Http\Resources\V1;

use App\Models\Credential;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Credential
 */
class CredentialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Never expose encrypted credential data through the API
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'last_used_at' => $this->last_used_at,
            'expires_at' => $this->expires_at,
            'is_expired' => $this->isExpired(),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
