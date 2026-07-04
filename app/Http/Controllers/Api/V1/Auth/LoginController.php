<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * Authenticate a user and issue a token pair.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
        );

        return $this->successResponse(
            'Login successful.',
            [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ],
        );
    }
}
