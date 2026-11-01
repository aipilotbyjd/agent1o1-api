<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Referrals;

use App\Models\Referrals\ReferralRewardRule;
use App\Services\Referrals\ReferralRuleDescriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReferralRewardRule
 */
class ReferralRewardRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'program_id' => $this->program_id,
            'name' => $this->name,
            'description' => $this->description,
            'summary' => app(ReferralRuleDescriber::class)->describe($this->resource),
            'is_active' => $this->is_active,
            'is_live' => $this->isLive(),
            'sort_order' => $this->sort_order,
            'trigger' => $this->trigger,
            'recipient' => $this->recipient,
            'reward_type' => $this->reward_type,
            'credits_amount' => $this->credits_amount,
            'plan_id' => $this->plan_id,
            'duration_days' => $this->duration_days,
            'amount_cents' => $this->amount_cents,
            'amount_percent_of_plan' => $this->amount_percent_of_plan,
            'trial_days' => $this->trial_days,
            'milestone_count' => $this->milestone_count,
            'hold_days' => $this->hold_days,
            'if_already_on_plan' => $this->if_already_on_plan,
            'fallback_credits' => $this->fallback_credits,
            'max_per_recipient' => $this->max_per_recipient,
            'conditions' => $this->conditions ?? (object) [],
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
