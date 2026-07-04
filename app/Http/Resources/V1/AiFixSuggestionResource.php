<?php

namespace App\Http\Resources\V1;

use App\Models\AiFixSuggestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiFixSuggestion
 */
class AiFixSuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'execution_id' => $this->execution_id,
            'node_id' => $this->node_id,
            'node_type' => $this->node_type,
            'diagnosis' => $this->diagnosis,
            'suggestions' => $this->suggestions,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
