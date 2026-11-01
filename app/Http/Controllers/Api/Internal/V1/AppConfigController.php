<?php

namespace App\Http\Controllers\Api\Internal\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Assistant\Branding\BrandRepository;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated: what the frontend needs before anyone signs in
 * (login screens already show the assistant's name). Short-cached so a
 * rename reaches browsers within minutes without a frontend deploy.
 */
class AppConfigController extends Controller
{
    public function __invoke(BrandRepository $brands): JsonResponse
    {
        return ApiResponse::success([
            'brand' => $brands->current()->toArray(),
        ])->setPublic()->setMaxAge(300);
    }
}
