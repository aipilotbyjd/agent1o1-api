<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\Reflection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reflection
 */
class ReflectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'reflection_run_id' => $this->reflection_run_id,
            'type' => $this->type->value,
            'title' => $this->title,
            'rationale' => $this->rationale,
            'evidence' => $this->evidence,
            'confidence' => $this->confidence,
            'support_count' => $this->support_count,
            'proposed_prompt' => $this->proposed_prompt,
            'target_skill_id' => $this->target_skill_id,
            'status' => $this->status->value,
            'applied_run_id' => $this->applied_run_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
