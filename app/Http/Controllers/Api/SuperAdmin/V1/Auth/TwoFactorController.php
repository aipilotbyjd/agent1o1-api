<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\Auth\SuperAdminAuthService;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(private readonly SuperAdminAuthService $auth) {}

    public function enable(Request $request)
    {
        $admin = $request->user('super-admin');

        abort_if(
            $admin->hasTwoFactorEnabled(),
            409,
            'Two-factor authentication is already enabled. Ask an owner to reset it.',
        );

        return ApiResponse::success(
            $this->auth->enableTwoFactor($admin),
            'Add the secret to your authenticator app, then confirm with a code.',
        );
    }

    public function confirm(ConfirmTwoFactorRequest $request)
    {
        $recoveryCodes = $this->auth->confirmTwoFactor($request->user('super-admin'), $request->validated('code'));

        return ApiResponse::success(
            ['recovery_codes' => $recoveryCodes],
            'Two-factor authentication is on. Save these recovery codes, then sign in again.',
        );
    }

    public function regenerateRecoveryCodes(StepUpRequest $request)
    {
        return ApiResponse::success(
            ['recovery_codes' => $this->auth->regenerateRecoveryCodes($request->user('super-admin'))],
            'New recovery codes generated. The old ones no longer work.',
        );
    }
}
