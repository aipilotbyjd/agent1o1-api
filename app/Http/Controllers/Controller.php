<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    use ApiResponse, AuthorizesRequests;

    protected function requirePermission(Permission $permission): ?JsonResponse
    {
        $user = Auth::user();

        if (! $user || ! $user->can($permission->value)) {
            return $this->errorResponse('Forbidden.', 403);
        }

        return null;
    }
}
