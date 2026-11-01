<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Http\Resources\Api\Internal\V1\Artifacts\ArtifactResource;
use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowBuilderMessage
 */
class WorkflowBuilderMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'draft_version_id' => $this->draft_version_id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'mode' => $this->mode,
            'context' => $this->context,
            'model_catalog_id' => $this->model_catalog_id,
            'model' => $this->whenLoaded('modelCatalog', fn (): ?array => $this->modelCatalog === null ? null : [
                'id' => $this->modelCatalog->id,
                'slug' => $this->modelCatalog->slug,
                'display_name' => $this->modelCatalog->is_internal ? 'Builder default' : $this->modelCatalog->display_name,
                'brand' => $this->modelCatalog->is_internal ? null : $this->modelCatalog->brand,
                'is_default' => (bool) $this->modelCatalog->is_internal,
            ]),
            'content' => $this->content,
            'actions' => $this->actions,
            'processing_status' => $this->processing_status,
            'error_message' => $this->error_message,
            'attachments' => ArtifactResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
        ];
    }
}
