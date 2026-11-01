<?php

namespace App\Http\Controllers\Api\Internal\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Notifications\NotificationResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated(NotificationResource::collection($notifications));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $databaseNotification = $request->user()->notifications()->findOrFail($notification);
        $databaseNotification->markAsRead();

        return ApiResponse::success(new NotificationResource($databaseNotification), 'Notification marked read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success(message: 'All notifications marked read.');
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->findOrFail($notification)->delete();

        return ApiResponse::success(message: 'Notification deleted.');
    }
}
