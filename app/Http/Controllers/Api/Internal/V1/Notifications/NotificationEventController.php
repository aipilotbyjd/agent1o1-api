<?php

namespace App\Http\Controllers\Api\Internal\V1\Notifications;

use App\Enums\Notifications\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * What every notification is: everything the app needs to render the bell
 * and the settings page without knowing about any one event.
 */
class NotificationEventController extends Controller
{
    /**
     * The flat list of events.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(NotificationType::catalog());
    }

    /**
     * Events grouped by category, in display order.
     */
    public function catalog(): JsonResponse
    {
        return ApiResponse::success(['categories' => NotificationType::categorizedCatalog()]);
    }
}
