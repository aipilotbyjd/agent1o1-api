<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tag
 */
class TagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'color' => $this->color,
            'workflow_count' => $this->whenCounted('workflows'),
            'agent_count' => $this->whenCounted('agents'),
            'created_at' => $this->created_at,
        ];
    }
}
