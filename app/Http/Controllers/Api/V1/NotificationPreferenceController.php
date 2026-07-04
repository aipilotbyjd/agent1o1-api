<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkspaceView)) {
            return $forbidden;
        }

        $preferences = NotificationPreference::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $request->user()->id)
            ->get();

        return $this->successResponse('Notification preferences retrieved.', $preferences->toArray());
    }

    public function upsert(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkspaceView)) {
            return $forbidden;
        }

        $data = $request->validate([
            'event_key' => ['required', 'string', 'max:100'],
            'in_app' => ['nullable', 'boolean'],
            'email' => ['nullable', 'boolean'],
            'channel_ids' => ['nullable', 'array'],
            'channel_ids.*' => ['uuid'],
        ]);

        $preference = NotificationPreference::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $request->user()->id,
                'event_key' => $data['event_key'],
            ],
            [
                'in_app' => $data['in_app'] ?? true,
                'email' => $data['email'] ?? false,
                'channel_ids' => $data['channel_ids'] ?? null,
            ],
        );

        return $this->successResponse('Notification preference saved.', $preference->toArray());
    }
}
