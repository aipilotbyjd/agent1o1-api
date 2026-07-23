<?php

namespace App\Http\Resources\V1;

use App\Models\InternalAgentRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InternalAgentRun
 */
class InternalAgentRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'provider' => $this->provider,
            'model' => $this->model,
            'prompt_tokens' => $this->prompt_tokens,
            'completion_tokens' => $this->completion_tokens,
            'total_tokens' => $this->total_tokens,
            'estimated_cost' => $this->estimated_cost !== null ? (float) $this->estimated_cost : null,
            'duration_ms' => $this->duration_ms,
            'status' => $this->status,
            'error' => $this->error,
            'created_at' => $this->created_at,
        ];
    }
}
