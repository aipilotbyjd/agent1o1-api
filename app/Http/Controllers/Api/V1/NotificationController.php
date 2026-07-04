<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\InAppNotificationResource;
use App\Models\InAppNotification;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkspaceView)) {
            return $forbidden;
        }

        $notifications = $this->scope($request, $workspace)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate((int) $request->query('per_page', 25));

        return $this->paginatedResponse('Notifications retrieved.', InAppNotificationResource::collection($notifications));
    }

    public function unreadCount(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkspaceView)) {
            return $forbidden;
        }

        return $this->successResponse('Unread count retrieved.', [
            'unread' => $this->scope($request, $workspace)->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(Request $request, Workspace $workspace, InAppNotification $notification): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkspaceView)) {
            return $forbidden;
        }

        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update(['read_at' => now()]);

        return $this->successResponse('Notification marked read.', new InAppNotificationResource($notification));
    }

    public function markAllRead(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkspaceView)) {
            return $forbidden;
        }

        $this->scope($request, $workspace)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->successResponse('All notifications marked read.');
    }

    public function destroy(Request $request, Workspace $workspace, InAppNotification $notification): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkspaceView)) {
            return $forbidden;
        }

        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->delete();

        return $this->successResponse('Notification deleted.');
    }

    private function scope(Request $request, Workspace $workspace)
    {
        return InAppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('workspace_id', $workspace->id);
    }
}
