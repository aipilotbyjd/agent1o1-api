<?php

namespace App\Http\Resources\V1;

use App\Models\Artifact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin Artifact
 */
class ArtifactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'version' => $this->version,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'agent' => [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
            ],
            'creator' => new UserResource($this->whenLoaded('creator')),
            'preview_url' => $this->isPreviewable()
                ? URL::temporarySignedRoute('v1.artifacts.preview', now()->addMinutes(15), ['artifact' => $this->id])
                : null,
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
