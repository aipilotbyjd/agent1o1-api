<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\AgentEvaluationSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentEvaluationSettings
 */
class AgentEvaluationSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'is_enabled' => $this->is_enabled,
            'model' => $this->model,
            'sentiment_enabled' => $this->sentiment_enabled,
            'sentiment_affects_grade' => $this->sentiment_affects_grade,
            'sentiment_guidance' => $this->sentiment_guidance,
            'suggest_tags_automatically' => $this->suggest_tags_automatically,
            'criteria' => $this->criteria ?? [],
            'tags' => $this->tags ?? [],
            'data_points' => $this->data_points ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
