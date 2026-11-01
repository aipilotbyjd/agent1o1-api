<?php

namespace App\Http\Controllers\Api\Internal\V1\Notifications;

use App\Enums\Notifications\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class NotificationEventController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(NotificationEvent::catalog());
    }
}
