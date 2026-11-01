<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Referrals;

use App\Models\Referrals\ReferralCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReferralCode
 */
class AdminReferralCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'program_id' => $this->program_id,
            'is_active' => $this->is_active,
            'max_uses' => $this->max_uses,
            'expires_at' => $this->expires_at,
            'reward_workspace_id' => $this->reward_workspace_id,
            'rule_multiplier' => $this->rule_multiplier,
            'referrals_count' => $this->whenCounted('referrals'),
            'visits_count' => $this->whenCounted('visits'),
            'created_at' => $this->created_at,
        ];
    }
}
