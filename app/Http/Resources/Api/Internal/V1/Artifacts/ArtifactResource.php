<?php

namespace App\Http\Resources\Api\Internal\V1\Artifacts;

use App\Http\Resources\Api\Internal\V1\User\UserResource;
use App\Models\Artifacts\Artifact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin Artifact
 */
class ArtifactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'version' => $this->version,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            // Null for a member upload, only agent-exported artifacts have
            // an agent behind them.
            'agent' => $this->agent === null ? null : [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
            ],
            // The personal-assistant conversation that made it, if any.
            'assistant_session_id' => $this->assistant_session_id,
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'preview_url' => $this->isPreviewable()
                ? URL::temporarySignedRoute('artifacts.preview', now()->addMinutes(15), ['artifact' => $this->id])
                : null,
            'general_access' => $this->general_access->value,
            'shared_with' => $this->whenLoaded('shares', fn () => $this->shares->map(fn ($share) => [
                'user_id' => $share->user_id,
                'user' => $share->relationLoaded('user') ? UserResource::make($share->user) : null,
            ])),
            'versions_count' => $this->whenCounted('versions'),
            'versions' => $this->whenLoaded('versions', fn () => $this->versions->map(fn ($v) => [
                'id' => $v->id,
                'version' => $v->version,
                'size' => $v->size,
                'created_at' => $v->created_at,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
