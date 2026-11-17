<?php

namespace App\Http\Resources\Api\Internal\V1\Notifications;

use App\Models\Notifications\NotificationDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One delivery to a channel. The payload itself isn't returned: it can carry
 * customer data and the channel's own history is enough to debug with.
 *
 * @mixin NotificationDelivery
 */
class NotificationDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_key' => $this->event_key,
            'channel' => $this->channel,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'error' => $this->error,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
        ];
    }
}
