<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Pricing;

use App\Models\Billing\PricingChangeLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PricingChangeLog
 */
class PricingChangeLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'subject_type' => $this->subject_type,
            'subject_key' => $this->subject_key,
            'action' => $this->action,
            'actor' => $this->whenLoaded('superAdmin', fn () => $this->superAdmin === null ? null : [
                'id' => $this->superAdmin->id,
                'name' => $this->superAdmin->name,
            ]),
            'before' => $this->before,
            'after' => $this->after,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
