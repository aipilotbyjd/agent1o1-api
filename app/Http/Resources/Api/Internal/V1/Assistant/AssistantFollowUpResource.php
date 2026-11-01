<?php

namespace App\Http\Resources\Api\Internal\V1\Assistant;

use App\Models\Assistant\AssistantFollowUp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssistantFollowUp
 */
class AssistantFollowUpResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'note' => $this->note,
            'status' => $this->status->value,
            'remind_at' => $this->remind_at,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
        ];
    }
}
