<?php

namespace App\Http\Resources\Api\Internal\V1\Workspaces;

use App\Enums\Workspaces\Permission;
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
        $role = $this->resolveViewerRole();

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
            'permissions' => $role
                ? array_map(fn (Permission $permission) => $permission->value, $role->permissions())
                : [],
            'workflows_count' => $this->whenCounted('workflows'),
            'agents_count' => $this->whenCounted('agents'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The viewer's role: set explicitly, or read from the membership pivot.
     */
    private function resolveViewerRole(): ?Role
    {
        if ($this->viewerRole !== null) {
            return $this->viewerRole;
        }

        $pivot = $this->resource->pivot ?? null;

        if ($pivot === null || $pivot->getTable() !== 'workspace_members') {
            return null;
        }

        return Role::tryFrom((string) $pivot->role);
    }
}
