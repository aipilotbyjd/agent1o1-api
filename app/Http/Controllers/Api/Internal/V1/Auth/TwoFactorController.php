<?php

namespace App\Http\Controllers\Api\Internal\V1\Auth;

use App\Enums\Auth\AuthEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Api\Internal\V1\Auth\DisableTwoFactorRequest;
use App\Http\Requests\Api\Internal\V1\Auth\EnableTwoFactorRequest;
use App\Http\Requests\Api\Internal\V1\Auth\RegenerateRecoveryCodesRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthEventRecorder;
use App\Services\Auth\TwoFactorAuthService;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthService $twoFactor,
        private readonly AuthEventRecorder $events,
    ) {}

    public function enable(EnableTwoFactorRequest $request)
    {
        $result = $this->twoFactor->enable($request->user());

        return ApiResponse::success(
            $result,
            'Scan the QR code with your authenticator app, then confirm with a code to finish enabling two-factor authentication.',
        );
    }

    public function confirm(ConfirmTwoFactorRequest $request)
    {
        $recoveryCodes = $this->twoFactor->confirm($request->user(), $request->validated('code'));

        $this->events->record(AuthEvent::TwoFactorEnabled, $request->user());

        return ApiResponse::success(
            ['recovery_codes' => $recoveryCodes],
            'Two-factor authentication enabled. Store these recovery codes somewhere safe: they will not be shown again.',
        );
    }

    public function disable(DisableTwoFactorRequest $request)
    {
        $this->twoFactor->disable($request->user());

        $this->events->record(AuthEvent::TwoFactorDisabled, $request->user());

        return ApiResponse::noContent();
    }

    /**
     * Note: recovery codes are hashed at rest, so this only reflects how many remain,
     * not their plaintext values: those are only ever shown once, at confirm/regenerate time.
     */
    public function recoveryCodes(Request $request)
    {
        $remaining = count($this->twoFactor->recoveryCodes($request->user()));

        return ApiResponse::success(['recovery_codes_remaining' => $remaining]);
    }

    public function regenerateRecoveryCodes(RegenerateRecoveryCodesRequest $request)
    {
        $recoveryCodes = $this->twoFactor->regenerateRecoveryCodes($request->user());

        $this->events->record(AuthEvent::RecoveryCodesRegenerated, $request->user());

        return ApiResponse::success(
            ['recovery_codes' => $recoveryCodes],
            'Recovery codes regenerated. Store these somewhere safe: they will not be shown again.',
        );
    }
}
