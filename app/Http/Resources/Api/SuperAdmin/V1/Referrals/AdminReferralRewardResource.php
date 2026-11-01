<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Referrals;

use App\Http\Resources\Api\Internal\V1\Referrals\ReferralRewardResource;
use Illuminate\Http\Request;

/**
 * The ledger entry plus the admin-only detail: who received it, the frozen
 * rule, clawback and audit fields.
 */
class AdminReferralRewardResource extends ReferralRewardResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'referral_id' => $this->referral_id,
            'rule_id' => $this->rule_id,
            'rule_snapshot' => $this->rule_snapshot,
            'recipient' => $this->whenLoaded('recipient', fn () => [
                'id' => $this->recipient?->id,
                'name' => $this->recipient?->name,
                'email' => $this->recipient?->email,
            ]),
            'plan_grant_id' => $this->plan_grant_id,
            'stripe_balance_transaction_id' => $this->stripe_balance_transaction_id,
            'credits_clawed_back' => $this->credits_clawed_back,
            'payment_reference' => $this->payment_reference,
            'granted_by' => $this->granted_by,
            'notes' => $this->notes,
        ];
    }
}
