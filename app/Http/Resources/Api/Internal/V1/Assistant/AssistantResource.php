<?php

namespace App\Http\Resources\Api\Internal\V1\Assistant;

use App\Models\Assistant\Assistant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Assistant
 */
class AssistantResource extends JsonResource
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
            'model_catalog_id' => $this->model_catalog_id,
            'instructions' => $this->instructions,
            'settings' => $this->settings ?? (object) [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
