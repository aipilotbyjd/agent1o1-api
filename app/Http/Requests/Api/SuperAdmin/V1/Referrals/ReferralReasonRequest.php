<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Referrals;

use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;

/**
 * Rejecting a referral and revoking a reward both need a reason on record.
 * Same rules as every other Super Admin reason, except the length cap: the
 * reason lands in `referrals.rejection_reason` / `referral_rewards.revoked_reason`,
 * which are 255-character columns.
 */
class ReferralReasonRequest extends ReasonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:'.config('super_admin_security.step_up.reason_min_length'), 'max:255'],
        ];
    }
}
