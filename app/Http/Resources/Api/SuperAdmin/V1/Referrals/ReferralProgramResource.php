<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Referrals;

use App\Models\Referrals\ReferralProgram;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReferralProgram
 */
class ReferralProgramResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'is_live' => $this->isLive(),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'attribution_window_days' => $this->attribution_window_days,
            'claim_window_hours' => $this->claim_window_hours,
            'require_verified_email' => $this->require_verified_email,
            'activation_event' => $this->activation_event,
            'activation_min_count' => $this->activation_min_count,
            'activation_window_days' => $this->activation_window_days,
            'default_hold_days' => $this->default_hold_days,
            'approval_mode' => $this->approval_mode,
            'revoke_on_partial_refund' => $this->revoke_on_partial_refund,
            'referrer_monthly_credit_cap' => $this->referrer_monthly_credit_cap,
            'referrer_max_stacked_plan_days' => $this->referrer_max_stacked_plan_days,
            'referrer_max_referrals_per_month' => $this->referrer_max_referrals_per_month,
            'referrer_min_account_age_days' => $this->referrer_min_account_age_days,
            'referrer_eligible_plan_ids' => $this->referrer_eligible_plan_ids ?? [],
            'fraud_checks' => $this->resolvedFraudChecks(),
            'rules' => ReferralRewardRuleResource::collection($this->whenLoaded('rules')),
            'rules_count' => $this->whenCounted('rules'),
            'referrals_count' => $this->whenCounted('referrals'),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
