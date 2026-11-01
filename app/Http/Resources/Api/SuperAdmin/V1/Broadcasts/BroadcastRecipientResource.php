<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Broadcasts;

use App\Models\Broadcasts\BroadcastRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BroadcastRecipient
 */
class BroadcastRecipientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'email' => $this->email,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'sent_at' => $this->sent_at,
            'error' => $this->error,
        ];
    }
}
