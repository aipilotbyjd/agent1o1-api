<?php

namespace App\Http\Resources\Api\Internal\V1\Referrals;

use App\Models\Referrals\ReferralReward;
use App\Services\Referrals\ReferralRuleDescriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReferralReward
 */
class ReferralRewardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'summary' => app(ReferralRuleDescriber::class)->rewardSummary($this->resource),
            'trigger' => $this->trigger,
            'recipient_role' => $this->recipient_role,
            'reward_type' => $this->reward_type,
            'credits' => $this->credits,
            'plan_id' => $this->plan_id,
            'plan_name' => $this->whenLoaded('plan', fn () => $this->plan?->name),
            'duration_days' => $this->duration_days,
            'amount_cents' => $this->amount_cents,
            'trial_days' => $this->trial_days,
            'status' => $this->status,
            'grant_after' => $this->grant_after,
            'granted_at' => $this->granted_at,
            'revoked_at' => $this->revoked_at,
            'revoked_reason' => $this->revoked_reason,
            'workspace_id' => $this->workspace_id,
            'created_at' => $this->created_at,
        ];
    }
}
