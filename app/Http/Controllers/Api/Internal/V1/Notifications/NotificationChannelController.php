<?php

namespace App\Http\Controllers\Api\Internal\V1\Notifications;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Notifications\StoreNotificationChannelRequest;
use App\Http\Requests\Api\Internal\V1\Notifications\UpdateNotificationChannelRequest;
use App\Http\Resources\Api\Internal\V1\Notifications\NotificationChannelResource;
use App\Http\Responses\ApiResponse;
use App\Models\Notifications\NotificationChannel;
use App\Models\Workspaces\Workspace;
use App\Notifications\Channels\WorkspaceWebhookChannel;
use Illuminate\Http\JsonResponse;

class NotificationChannelController extends Controller
{
    public function index(Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::NotificationChannelView);

        return ApiResponse::success([
            'channels' => NotificationChannelResource::collection($workspace->notificationChannels()->latest()->get()),
        ]);
    }

    public function store(StoreNotificationChannelRequest $request, Workspace $workspace): JsonResponse
    {
        $channel = $workspace->notificationChannels()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return ApiResponse::created(['channel' => new NotificationChannelResource($channel)], 'Notification channel created.');
    }

    public function update(UpdateNotificationChannelRequest $request, Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $notificationChannel);
        $notificationChannel->update($request->validated());

        return ApiResponse::success(['channel' => new NotificationChannelResource($notificationChannel)], 'Notification channel updated.');
    }

    public function destroy(Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $notificationChannel);
        $this->requirePermission(Permission::NotificationChannelManage);
        $notificationChannel->delete();

        return ApiResponse::success(message: 'Notification channel deleted.');
    }

    public function test(Workspace $workspace, NotificationChannel $notificationChannel, WorkspaceWebhookChannel $webhookChannel): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $notificationChannel);
        $this->requirePermission(Permission::NotificationChannelManage);

        $result = $webhookChannel->deliverTest($notificationChannel);

        return $result['ok']
            ? ApiResponse::success(message: $result['message'])
            : ApiResponse::error($result['message']);
    }
}
