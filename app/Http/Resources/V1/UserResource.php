<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar ? asset('storage/'.$this->avatar) : null,
            'job_role' => $this->job_role?->value,
            'discovery_source' => $this->discovery_source?->value,
            'is_admin' => $this->is_admin,
            'email_verified_at' => $this->email_verified_at,
            'onboarding_dismissed_at' => $this->onboarding_dismissed_at,
            'current_workspace' => $this->whenLoaded('currentWorkspace', fn () => [
                'id' => $this->currentWorkspace->id,
                'name' => $this->currentWorkspace->name,
                'slug' => $this->currentWorkspace->slug,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
