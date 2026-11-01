<?php

namespace App\Http\Resources\Api\Internal\V1\Assistant;

use App\Models\Assistant\AssistantSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssistantSession
 */
class AssistantSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assistant_id' => $this->assistant_id,
            'title' => $this->title,
            'status' => $this->status->value,
            'origin' => $this->origin->value,
            'incognito' => $this->incognito,
            'expires_at' => $this->expires_at,
            'last_activity_at' => $this->last_activity_at,
            'messages_count' => $this->whenCounted('messages'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
