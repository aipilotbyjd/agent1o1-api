<?php

namespace App\Http\Resources\V1;

use App\Models\AiAgentStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiAgentStep
 */
class AiAgentStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'step_number' => $this->step_number,
            'action' => $this->action,
            'tool_name' => $this->tool_name,
            'tool_input' => $this->tool_input,
            'tool_output' => $this->tool_output,
            'llm_reasoning' => $this->llm_reasoning,
            'tokens_used' => $this->tokens_used,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at,
        ];
    }
}
