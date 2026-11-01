<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\WorkflowPageGeneration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowPageGeneration
 */
class WorkflowPageGenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'variant' => $this->variant,
            'keep_theme' => $this->keep_theme,
            'status' => $this->status,
            'content' => $this->content,
            'error' => $this->error,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
