<?php

namespace App\Http\Resources\V1;

use App\Models\AgentSkillReference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentSkillReference
 */
class AgentSkillReferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'sort_order' => $this->sort_order,
        ];
    }
}
