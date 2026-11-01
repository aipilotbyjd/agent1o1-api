<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Pricing;

use App\Models\Billing\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class PlanSubscriberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace' => $this->whenLoaded('workspace', fn () => $this->workspace === null ? null : [
                'id' => $this->workspace->id,
                'name' => $this->workspace->name,
                'slug' => $this->workspace->slug,
            ]),
            'status' => $this->stripe_status,
            'stripe_price' => $this->stripe_price,
            'trial_ends_at' => $this->trial_ends_at,
            'ends_at' => $this->ends_at,
            'created_at' => $this->created_at,
        ];
    }
}
