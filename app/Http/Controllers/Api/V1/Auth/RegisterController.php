<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * Register a new user and issue an initial token pair.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->successResponse(
            'Registration successful.',
            [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ],
            201,
        );
    }
}
