<?php

namespace App\Http\Resources\Api\Internal\V1\Billing;

use App\Models\Billing\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'stripe_status' => $this->stripe_status,
            'trial_ends_at' => $this->trial_ends_at,
            'ends_at' => $this->ends_at,
            'plan' => PlanResource::make($this->whenLoaded('plan')),
        ];
    }
}
