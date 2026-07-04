<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RefreshTokenRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class RefreshTokenController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * Exchange a refresh token for a new token pair.
     */
    public function __invoke(RefreshTokenRequest $request): JsonResponse
    {
        $token = $this->authService->refresh($request->validated('refresh_token'));

        return $this->successResponse(
            'Token refreshed successfully.',
            ['token' => $token],
        );
    }
}
