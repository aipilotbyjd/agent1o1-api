<?php

namespace App\Http\Resources\Api\Internal\V1\User;

use App\Http\Resources\Api\Internal\V1\Workspaces\WorkspaceResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'pending_email' => $this->pending_email,
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'avatar' => $this->avatar ? Storage::disk('public')->url($this->avatar) : null,
            'timezone' => $this->timezone,
            'current_workspace_id' => $this->current_workspace_id,
            'current_workspace' => WorkspaceResource::make($this->whenLoaded('currentWorkspace')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
