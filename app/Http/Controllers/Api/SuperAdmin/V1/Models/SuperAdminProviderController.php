<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Models;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\Models\SuperAdminProviderPauseService;

/**
 * The provider kill switch: stop the platform using a provider everywhere at
 * once, and start again. See `SuperAdminProviderPauseService`.
 */
class SuperAdminProviderController extends Controller
{
    public function __construct(private readonly SuperAdminProviderPauseService $pauses) {}

    /**
     * Needs a reason and a fresh authenticator code: it changes what every
     * customer can use, straight away.
     */
    public function pause(StepUpRequest $request, string $provider)
    {
        $pause = $this->pauses->pause($request->user('super-admin'), $provider, $request->validated('reason'));

        return ApiResponse::success([
            'provider' => $pause->provider,
            'reason' => $pause->reason,
            'paused_at' => $pause->paused_at,
        ], 'Provider paused. Calls on the platform\'s key for it stop now; workspaces\' own keys are unaffected.');
    }

    public function resume(ReasonRequest $request, string $provider)
    {
        $this->pauses->resume($request->user('super-admin'), $provider, $request->validated('reason'));

        return ApiResponse::success(null, 'Provider resumed.');
    }
}
