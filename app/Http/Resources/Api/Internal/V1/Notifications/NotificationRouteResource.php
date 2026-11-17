<?php

namespace App\Http\Resources\Api\Internal\V1\Notifications;

use App\Models\Notifications\NotificationRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationRoute
 */
class NotificationRouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notification_channel_id' => $this->notification_channel_id,
            'channel' => $this->whenLoaded('channel', fn () => $this->channel === null ? null : [
                'id' => $this->channel->id,
                'type' => $this->channel->type,
                'name' => $this->channel->name,
                'is_active' => $this->channel->is_active,
            ]),
            'event_key' => $this->event_key?->value,
            'category' => $this->category?->value,
            'min_severity' => $this->min_severity?->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
