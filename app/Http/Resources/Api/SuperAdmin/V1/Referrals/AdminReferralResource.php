<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Referrals;

use App\Http\Resources\Api\Internal\V1\Referrals\ReferralRewardResource;
use App\Models\Referrals\Referral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full referral, unmasked, for platform admins.
 *
 * @mixin Referral
 */
class AdminReferralResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'program_id' => $this->program_id,
            'code' => $this->whenLoaded('code', fn () => $this->code?->code),
            'referrer' => $this->whenLoaded('referrer', fn () => [
                'id' => $this->referrer?->id,
                'name' => $this->referrer?->name,
                'email' => $this->referrer?->email,
            ]),
            'referred_user' => $this->whenLoaded('referredUser', fn () => [
                'id' => $this->referredUser?->id,
                'name' => $this->referredUser?->name,
                'email' => $this->referredUser?->email,
            ]),
            'referred_workspace_id' => $this->referred_workspace_id,
            'status' => $this->status,
            'verified_at' => $this->verified_at,
            'activated_at' => $this->activated_at,
            'converted_at' => $this->converted_at,
            'rejected_at' => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,
            'rewards' => ReferralRewardResource::collection($this->whenLoaded('rewards')),
            'payments_count' => $this->whenCounted('payments'),
            'created_at' => $this->created_at,
        ];
    }
}
