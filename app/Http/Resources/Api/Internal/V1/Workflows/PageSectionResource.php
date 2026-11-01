<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\PageSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PageSection
 */
class PageSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'block' => $this->block,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
