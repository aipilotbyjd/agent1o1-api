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
use App\Services\Notifications\IntegrationDeliverer;
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

        return ApiResponse::created(['channel' => (new NotificationChannelResource($channel))->withSigningSecret()], 'Notification channel created.');
    }

    public function update(UpdateNotificationChannelRequest $request, Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $notificationChannel);
        $notificationChannel->fill($request->validated());

        // Switching a channel back on starts its failure count afresh.
        if ($notificationChannel->isDirty('is_active') && $notificationChannel->is_active) {
            $notificationChannel->forceFill(['consecutive_failures' => 0, 'disabled_at' => null]);
        }

        $notificationChannel->save();

        return ApiResponse::success(['channel' => new NotificationChannelResource($notificationChannel)], 'Notification channel updated.');
    }

    public function destroy(Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $notificationChannel);
        $this->requirePermission(Permission::NotificationChannelManage);
        $notificationChannel->delete();

        return ApiResponse::success(message: 'Notification channel deleted.');
    }

    /**
     * Replaces a webhook's signing secret, e.g. after it leaked. The new one
     * is shown once, in this response.
     */
    public function rotateSecret(Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $notificationChannel);
        $this->requirePermission(Permission::NotificationChannelManage);

        $notificationChannel->forceFill(['signing_secret' => NotificationChannel::newSigningSecret()])->save();

        return ApiResponse::success(['channel' => (new NotificationChannelResource($notificationChannel))->withSigningSecret()], 'Signing secret rotated.');
    }

    public function test(Workspace $workspace, NotificationChannel $notificationChannel, IntegrationDeliverer $deliverer): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $notificationChannel);
        $this->requirePermission(Permission::NotificationChannelManage);

        $result = $deliverer->deliverTest($notificationChannel);

        return $result['ok']
            ? ApiResponse::success(message: $result['message'])
            : ApiResponse::error($result['message']);
    }
}
