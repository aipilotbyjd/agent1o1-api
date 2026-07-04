<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        return $this->successResponse(
            'Platform settings retrieved.',
            PlatformSetting::query()->pluck('value', 'key')->all(),
        );
    }

    public function update(Request $request): JsonResponse
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $data = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        foreach ($data['settings'] as $key => $value) {
            PlatformSetting::set($key, $value);
        }

        return $this->successResponse(
            'Platform settings updated.',
            PlatformSetting::query()->pluck('value', 'key')->all(),
        );
    }

    private function requireAdmin(Request $request): ?JsonResponse
    {
        if (! $request->user()?->can('platformAdmin')) {
            return $this->errorResponse('Forbidden.', 403);
        }

        return null;
    }
}
