<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\SkillScript;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SkillScript
 */
class SkillScriptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'skill_id' => $this->skill_id,
            'name' => $this->name,
            'description' => $this->description,
            'language' => $this->language,
            'code' => $this->code,
            'is_enabled' => $this->is_enabled,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
