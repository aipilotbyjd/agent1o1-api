<?php

namespace App\Http\Resources\V1;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'billing_interval' => $this->billing_interval,
            'credits_per_cycle' => $this->credits_per_cycle,
            'trial_ends_at' => $this->trial_ends_at,
            'current_period_start' => $this->current_period_start,
            'current_period_end' => $this->current_period_end,
            'canceled_at' => $this->canceled_at,
            'plan' => new PlanResource($this->whenLoaded('plan')),
        ];
    }
}
