<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Models;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Models\ProviderConnectCredentialsRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Models\UpdateProviderSettingsRequest;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\Models\SuperAdminProviderPauseService;
use App\Services\SuperAdmin\Models\SuperAdminProviderSettingsService;

/**
 * The provider kill switch (stop the platform's own key for a provider
 * everywhere at once, and start again: `SuperAdminProviderPauseService`),
 * and the switches for customers' own keys (`SuperAdminProviderSettingsService`).
 */
class SuperAdminProviderController extends Controller
{
    public function __construct(
        private readonly SuperAdminProviderPauseService $pauses,
        private readonly SuperAdminProviderSettingsService $settings,
    ) {}

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

    /**
     * Changes the switches for customers' own keys (`AiProviderSettings`).
     * Turning customer keys off needs a fresh authenticator code, and can tell
     * the workspaces that have a key for it (`notify`).
     */
    public function update(UpdateProviderSettingsRequest $request, string $provider)
    {
        $setting = $this->settings->update(
            $request->user('super-admin'),
            $provider,
            $request->safe()->only(SuperAdminProviderSettingsService::FIELDS),
            $request->validated('reason'),
            $request->validated('notify', 'none'),
        );

        return ApiResponse::success(['provider' => $setting->provider], 'Provider settings saved.');
    }

    /**
     * What turning customer keys for the provider off would touch.
     */
    public function impact(string $provider)
    {
        return ApiResponse::success($this->settings->impact($provider));
    }

    public function setConnectCredentials(ProviderConnectCredentialsRequest $request, string $provider)
    {
        $this->settings->setConnectCredentials(
            $request->user('super-admin'),
            $provider,
            $request->validated('client_id'),
            $request->validated('client_secret'),
            $request->validated('reason'),
        );

        return ApiResponse::success(null, 'Login app saved.');
    }

    public function clearConnectCredentials(StepUpRequest $request, string $provider)
    {
        $this->settings->clearConnectCredentials($request->user('super-admin'), $provider, $request->validated('reason'));

        return ApiResponse::success(null, 'Login app removed. The .env values, if any, are used again.');
    }
}
