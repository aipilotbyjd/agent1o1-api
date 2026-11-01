<?php

namespace App\Http\Controllers\Api\Internal\V1\Referrals;

use App\Actions\Referrals\RecordReferralVisitAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Referrals\RecordReferralVisitRequest;
use App\Http\Responses\ApiResponse;

/**
 * Public: called by the frontend when someone lands on a `?ref=` link,
 * before they have an account.
 */
class ReferralVisitController extends Controller
{
    public function store(RecordReferralVisitRequest $request, RecordReferralVisitAction $recordVisit)
    {
        $result = $recordVisit->execute(
            $request->validated('code'),
            $request->safe()->except('code'),
            $request->ip(),
            $request->userAgent(),
        );

        if ($result === null) {
            return ApiResponse::notFound('This referral link is not valid.');
        }

        return ApiResponse::created($result, 'Referral visit recorded.');
    }
}
