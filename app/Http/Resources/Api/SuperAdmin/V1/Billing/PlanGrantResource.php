<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Billing;

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
            'workspace_id' => $this->workspace_id,
            'plan' => $this->whenLoaded('plan', fn () => $this->plan === null ? null : [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'slug' => $this->plan->slug,
            ]),
            'source' => $this->source->value,
            'status' => $this->status->value,
            'price_cents' => $this->price_cents,
            'granted_at' => $this->granted_at,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
