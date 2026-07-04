<?php

namespace App\Http\Resources\V1;

use App\Models\TriggerCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TriggerCategory
 */
class TriggerCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'category_type' => $this->category_type,
            'trigger_types' => TriggerTypeResource::collection($this->whenLoaded('triggerTypes')),
        ];
    }
}
