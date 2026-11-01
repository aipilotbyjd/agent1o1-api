<?php

namespace App\Http\Resources\Api\Internal\V1\Assistant;

use App\Models\Assistant\AssistantAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssistantAction
 */
class AssistantActionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tool_call_id' => $this->tool_call_id,
            'tool' => $this->tool,
            'arguments' => $this->arguments ?? (object) [],
            'effect' => $this->effect->value,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'decision_note' => $this->decision_note,
            'expires_at' => $this->expires_at,
        ];
    }
}
