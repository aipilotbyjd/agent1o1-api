<?php

namespace App\Http\Resources\V1;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workspace
 */
class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'settings' => $this->settings,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'role' => $request->attributes->get('workspace_role'),
            'member_count' => $this->whenCounted('members'),
            'workflows_count' => $this->whenCounted('workflows'),
            'agents_count' => $this->whenCounted('agents'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
