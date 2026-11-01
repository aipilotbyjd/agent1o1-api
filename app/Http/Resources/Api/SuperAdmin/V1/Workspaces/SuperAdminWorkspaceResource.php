<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Workspaces;

use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A workspace as a super admin sees it: ownership, size and state, never its
 * contents (workflows, secrets, runs' data).
 *
 * @mixin Workspace
 */
class SuperAdminWorkspaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner === null ? null : [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ]),
            'members_count' => $this->whenCounted('members'),
            'topup_credits' => $this->topup_credits,
            'stripe_customer' => $this->stripe_id !== null,
            'suspended_at' => $this->suspended_at,
            'suspended_reason' => $this->suspended_reason,
            'tags' => $this->when($this->resource->offsetExists('account_tags'), fn () => $this->account_tags),
            'created_at' => $this->created_at,
        ];
    }
}
