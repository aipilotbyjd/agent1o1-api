<?php

namespace App\Http\Controllers\Api\Internal\V1\Referrals;

use App\Actions\Referrals\AttributeReferralAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Referrals\ClaimReferralRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Referrals\ReferralCodes;
use App\Services\Referrals\ReferralSettings;

/**
 * Attributes a social signup (whose stateless OAuth round trip can't carry
 * the `?ref=` code) straight after the first login. Only a fresh account
 * with no referral yet may claim, within the program's claim window.
 */
class ReferralClaimController extends Controller
{
    public function store(ClaimReferralRequest $request, AttributeReferralAction $attribute, ReferralCodes $codes, ReferralSettings $settings)
    {
        $user = $request->user();

        if ($user->referral()->exists()) {
            return ApiResponse::error('Your account is already linked to a referral.', 409);
        }

        $code = $codes->findUsable($request->validated('code'));
        $program = $code?->program?->isLive() ? $code->program : $settings->defaultProgram();

        if ($code === null || $program === null) {
            return ApiResponse::notFound('This referral link is not valid.');
        }

        if ($user->created_at->copy()->addHours($program->claim_window_hours)->isPast()) {
            return ApiResponse::error('Referral links can only be claimed right after signing up.', 422);
        }

        $referral = $attribute->execute($user, $code->code, $request->validated('visitor_id'), $request->ip());

        if ($referral === null) {
            return ApiResponse::error('This referral could not be applied.', 422);
        }

        return ApiResponse::created([
            'status' => $referral->status,
            'accepted' => ! $referral->isRejected(),
        ], $referral->isRejected() ? 'This signup is not eligible for referral rewards.' : 'Referral applied.');
    }
}
