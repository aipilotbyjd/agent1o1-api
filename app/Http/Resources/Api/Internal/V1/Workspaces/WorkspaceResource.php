<?php

namespace App\Http\Resources\Api\Internal\V1\Workspaces;

use App\Enums\Workspaces\Role;
use App\Http\Resources\Api\Internal\V1\User\UserResource;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workspace
 */
class WorkspaceResource extends JsonResource
{
    private ?Role $viewerRole = null;

    public function withRole(?Role $role): static
    {
        $this->viewerRole = $role;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'avatar' => $this->avatar,
            'timezone' => $this->timezone,
            'owner_id' => $this->owner_id,
            'owner' => UserResource::make($this->whenLoaded('owner')),
            'role' => $this->viewerRole?->value
                ?? $this->whenPivotLoaded('workspace_members', fn () => $this->pivot->role),
            'workflows_count' => $this->whenCounted('workflows'),
            'agents_count' => $this->whenCounted('agents'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
