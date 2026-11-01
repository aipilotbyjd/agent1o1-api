<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Referrals;

use App\Enums\Referrals\ReferralTrigger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Referrals\SimulateReferralRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Referrals\ReferralProgram;
use App\Services\Referrals\ReferralSimulator;
use App\Services\Referrals\TriggerContext;

/**
 * "What would this program give for this event?" - without writing
 * anything.
 */
class ReferralSimulationController extends Controller
{
    public function store(SimulateReferralRequest $request, ReferralProgram $program, ReferralSimulator $simulator)
    {
        $results = $simulator->simulate(
            $program,
            ReferralTrigger::from($request->validated('trigger')),
            TriggerContext::fromArray($request->validated()),
            (float) ($request->validated('multiplier') ?? 1),
        );

        return ApiResponse::success([
            'program_id' => $program->id,
            'program_is_live' => $program->isLive(),
            'results' => $results,
        ]);
    }
}
