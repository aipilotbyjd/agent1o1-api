<?php

namespace App\Http\Controllers\Api\Internal\V1\Notifications;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Notifications\NotificationRouteRequest;
use App\Http\Resources\Api\Internal\V1\Notifications\NotificationRouteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Notifications\NotificationRoute;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;

/**
 * Which of a workspace's notifications reach which of its chat and webhook
 * channels.
 */
class NotificationRouteController extends Controller
{
    public function index(Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::NotificationChannelView);

        return ApiResponse::success([
            'routes' => NotificationRouteResource::collection(
                NotificationRoute::query()->whereBelongsTo($workspace)->with('channel')->oldest()->get(),
            ),
        ]);
    }

    public function store(NotificationRouteRequest $request, Workspace $workspace): JsonResponse
    {
        $route = NotificationRoute::query()->create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['route' => new NotificationRouteResource($route->load('channel'))], 'Notification route created.');
    }

    public function update(NotificationRouteRequest $request, Workspace $workspace, NotificationRoute $notificationRoute): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $notificationRoute);
        $notificationRoute->update($request->validated());

        return ApiResponse::success(['route' => new NotificationRouteResource($notificationRoute->load('channel'))], 'Notification route updated.');
    }

    public function destroy(Workspace $workspace, NotificationRoute $notificationRoute): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $notificationRoute);
        $this->requirePermission(Permission::NotificationChannelManage);
        $notificationRoute->delete();

        return ApiResponse::success(message: 'Notification route deleted.');
    }
}
