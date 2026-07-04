<?php

namespace App\Http\Resources\V1;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tag
 */
class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'workflow_count' => $this->whenCounted('workflows'),
            'created_at' => $this->created_at,
        ];
    }
}
