<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\System;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\System\NotificationHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationHealthController extends Controller
{
    public function __invoke(Request $request, NotificationHealth $health): JsonResponse
    {
        $window = max(1, min($request->integer('hours', 24), 24 * 30));

        return ApiResponse::success($health->report($window));
    }
}
