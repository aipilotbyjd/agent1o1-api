<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Users;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A platform user as a super admin sees them. Never carries the password,
 * two-factor material, tokens or the user's content.
 *
 * @mixin User
 */
class SuperAdminUserResource extends JsonResource
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
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'timezone' => $this->timezone,
            'suspended_at' => $this->suspended_at,
            'suspended_reason' => $this->suspended_reason,
            'workspaces_count' => $this->whenCounted('workspaces'),
            'workspaces' => $this->whenLoaded('workspaces', fn () => $this->workspaces->map(fn ($workspace): array => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'role' => $workspace->pivot->role,
                'suspended_at' => $workspace->suspended_at,
            ])->all()),
            'tags' => $this->when($this->resource->offsetExists('account_tags'), fn () => $this->account_tags),
            'created_at' => $this->created_at,
        ];
    }
}
