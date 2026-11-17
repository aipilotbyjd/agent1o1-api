<?php

namespace App\Http\Resources\Api\Internal\V1\Notifications;

use App\Models\Notifications\NotificationChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A chat or webhook channel and its health. The webhook signing secret is
 * only shown when it is created or rotated (`withSigningSecret()`), never in
 * a listing.
 *
 * @mixin NotificationChannel
 */
class NotificationChannelResource extends JsonResource
{
    private bool $includeSigningSecret = false;

    public function withSigningSecret(): static
    {
        $this->includeSigningSecret = true;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'consecutive_failures' => $this->consecutive_failures,
            'last_delivered_at' => $this->last_delivered_at,
            'last_failed_at' => $this->last_failed_at,
            'last_error' => $this->last_error,
            'disabled_at' => $this->disabled_at,
            'signing_secret' => $this->when($this->includeSigningSecret && $this->type === 'webhook', fn () => $this->signing_secret),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
