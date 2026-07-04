<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notification\StoreNotificationChannelRequest;
use App\Http\Requests\Api\V1\Notification\UpdateNotificationChannelRequest;
use App\Http\Resources\V1\NotificationChannelResource;
use App\Models\NotificationChannel;
use App\Models\Workspace;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationChannelController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::MemberView)) {
            return $denied;
        }

        return $this->successResponse(
            'Notification channels retrieved.',
            NotificationChannelResource::collection($workspace->notificationChannels()->latest()->get()),
        );
    }

    public function store(StoreNotificationChannelRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $notificationChannel = $workspace->notificationChannels()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'is_active' => $request->validated('is_active') ?? true,
        ]);

        return $this->successResponse('Notification channel created.', new NotificationChannelResource($notificationChannel), 201);
    }

    public function update(UpdateNotificationChannelRequest $request, Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $notificationChannel->update($request->validated());

        return $this->successResponse('Notification channel updated.', new NotificationChannelResource($notificationChannel));
    }

    public function destroy(Request $request, Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $notificationChannel->delete();

        return $this->successResponse('Notification channel deleted.');
    }

    public function test(Request $request, Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $result = $this->notifications->deliverToChannel($notificationChannel, 'Test notification from your workspace.');

        return $result['ok']
            ? $this->successResponse($result['message'])
            : $this->errorResponse($result['message'], 422);
    }
}
