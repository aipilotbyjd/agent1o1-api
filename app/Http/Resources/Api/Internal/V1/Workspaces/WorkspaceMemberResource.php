<?php

namespace App\Http\Resources\Api\Internal\V1\Workspaces;

use App\Http\Resources\Api\Internal\V1\User\UserResource;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkspaceMember
 */
class WorkspaceMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'invited_by' => $this->invited_by,
            'joined_at' => $this->joined_at,
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
