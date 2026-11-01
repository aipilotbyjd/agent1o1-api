<?php

namespace App\Http\Resources\Api\Internal\V1\Billing;

use App\Models\Billing\PlanGrant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlanGrant
 */
class PlanGrantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'status' => $this->status,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'granted_at' => $this->granted_at,
            'expires_at' => $this->expires_at,
            'plan' => PlanResource::make($this->whenLoaded('plan')),
        ];
    }
}
