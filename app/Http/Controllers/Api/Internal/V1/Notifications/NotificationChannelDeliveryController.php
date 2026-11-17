<?php

namespace App\Http\Controllers\Api\Internal\V1\Notifications;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Notifications\NotificationDeliveryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Notifications\NotificationChannel;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a chat or webhook channel was sent recently and how each delivery
 * went: the answer to "did Slack get it?".
 */
class NotificationChannelDeliveryController extends Controller
{
    public function index(Request $request, Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $notificationChannel);
        $this->requirePermission(Permission::NotificationChannelView);

        return ApiResponse::paginated(NotificationDeliveryResource::collection(
            $notificationChannel->deliveries()->latest()->paginate($this->perPage($request)),
        ));
    }
}
