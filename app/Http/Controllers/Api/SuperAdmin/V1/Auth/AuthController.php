<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Auth\LoginRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Auth\RefreshRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Auth\VerifyTwoFactorRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Accounts\SuperAdminAccountResource;
use App\Http\Resources\Api\SuperAdmin\V1\Auth\SuperAdminLoginEventResource;
use App\Http\Resources\Api\SuperAdmin\V1\Auth\SuperAdminSessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminToken;
use App\Services\SuperAdmin\Auth\SuperAdminAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AuthController extends Controller
{
    public function __construct(private readonly SuperAdminAuthService $auth) {}

    public function login(LoginRequest $request)
    {
        $result = $this->auth->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->ip(),
        );

        if ($result['stage'] === 'two_factor_challenge') {
            return ApiResponse::success(
                ['two_factor_challenge' => $result['challenge_token']],
                'Two-factor authentication code required.',
            );
        }

        return ApiResponse::success([
            'setup_required' => true,
            'admin' => (new SuperAdminAccountResource($result['admin']))->resolve(),
            'tokens' => $this->tokenPayload($result['tokens']),
        ], 'Set up two-factor authentication to continue.');
    }

    public function verifyTwoFactor(VerifyTwoFactorRequest $request)
    {
        $result = $this->auth->completeTwoFactorChallenge(
            $request->validated('challenge_token'),
            $request->validated('code'),
            $request->ip(),
        );

        return $this->respondWithTokens($result, 'Logged in successfully.');
    }

    public function refresh(RefreshRequest $request)
    {
        $result = $this->auth->refresh($request->validated('refresh_token'), $request->ip());

        return $this->respondWithTokens($result, 'Token refreshed.');
    }

    public function logout(Request $request)
    {
        $this->auth->logout($this->admin($request), $this->token($request));

        return ApiResponse::noContent();
    }

    public function logoutAll(Request $request)
    {
        $this->auth->logoutAll($this->admin($request));

        return ApiResponse::noContent();
    }

    public function me(Request $request)
    {
        return ApiResponse::success([
            'admin' => (new SuperAdminAccountResource($this->admin($request)))->resolve(),
            'token_scope' => $this->token($request)->scope,
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $this->auth->changePassword(
            $this->admin($request),
            $this->token($request),
            $request->validated('current_password'),
            $request->validated('password'),
        );

        return ApiResponse::success(null, 'Password changed. Your other sessions were signed out.');
    }

    public function sessions(Request $request)
    {
        $sessions = $this->admin($request)->tokens()
            ->where('scope', SuperAdminToken::SCOPE_FULL)
            ->whereNull('revoked_at')
            ->where('absolute_expires_at', '>', now())
            ->latest()
            ->get();

        return ApiResponse::success(SuperAdminSessionResource::collection($sessions)->resolve($request));
    }

    public function revokeSession(Request $request, string $token)
    {
        $this->auth->revokeSession($this->admin($request), $token);

        return ApiResponse::noContent();
    }

    public function events(Request $request)
    {
        $events = $this->admin($request)->loginEvents()
            ->latest('created_at')
            ->limit(100)
            ->get();

        return ApiResponse::success(SuperAdminLoginEventResource::collection($events)->resolve($request));
    }

    /**
     * @param  array{admin: SuperAdmin, tokens: array}  $result
     */
    private function respondWithTokens(array $result, string $message)
    {
        return ApiResponse::success([
            'admin' => (new SuperAdminAccountResource($result['admin']))->resolve(),
            'tokens' => $this->tokenPayload($result['tokens']),
        ], $message);
    }

    /**
     * @param  array<string, mixed>  $issued
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, token_type: string}
     */
    private function tokenPayload(array $issued): array
    {
        return Arr::only($issued, ['access_token', 'refresh_token', 'expires_in', 'token_type']);
    }

    private function admin(Request $request): SuperAdmin
    {
        return $request->user('super-admin');
    }

    private function token(Request $request): SuperAdminToken
    {
        return $request->attributes->get('super_admin_token');
    }
}
