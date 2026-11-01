<?php

namespace App\Http\Resources\Api\Internal\V1\Assistant;

use App\Models\Assistant\AssistantTurn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssistantTurn
 */
class AssistantTurnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assistant_session_id' => $this->assistant_session_id,
            'status' => $this->status->value,
            'user_message_id' => $this->user_message_id,
            'assistant_message_id' => $this->assistant_message_id,
            'error' => $this->error,
            'actions' => AssistantActionResource::collection($this->whenLoaded('actions')),
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
        ];
    }
}
