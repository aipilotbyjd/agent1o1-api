<?php

namespace App\Http\Resources\Api\Public\V1;

use App\Models\Agents\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Identity only. `instructions` is deliberately absent: it is the agent's
 * system prompt, which an invoking integrator has no need for and which
 * often carries internal policy.
 *
 * @mixin Agent
 */
class AgentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
