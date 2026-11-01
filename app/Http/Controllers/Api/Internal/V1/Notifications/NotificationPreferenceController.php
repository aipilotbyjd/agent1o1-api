<?php

namespace App\Http\Controllers\Api\Internal\V1\Notifications;

use App\Enums\Notifications\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Notifications\NotificationPreference;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $preferences = NotificationPreference::query()
            ->whereBelongsTo($workspace)
            ->whereBelongsTo($request->user())
            ->get();

        return ApiResponse::success(['preferences' => $preferences]);
    }

    public function upsert(Request $request, Workspace $workspace): JsonResponse
    {
        $data = $request->validate([
            'event_key' => ['required', Rule::enum(NotificationEvent::class)],
            'in_app' => ['nullable', 'boolean'],
            'email' => ['nullable', 'boolean'],
            'channel_ids' => ['nullable', 'array'],
            'channel_ids.*' => [
                'integer',
                Rule::exists('notification_channels', 'id')->where('workspace_id', $workspace->id),
            ],
        ]);

        $preference = NotificationPreference::query()->firstOrNew([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'event_key' => $data['event_key'],
        ]);

        // A partial update changes only what it names; a brand-new preference
        // starts from the event defaults.
        if (! $preference->exists) {
            $preference->fill([
                'in_app' => NotificationEvent::DEFAULT_IN_APP,
                'email' => NotificationEvent::DEFAULT_EMAIL,
                'channel_ids' => null,
            ]);
        }

        if ($request->has('in_app')) {
            $preference->in_app = $data['in_app'] ?? NotificationEvent::DEFAULT_IN_APP;
        }

        if ($request->has('email')) {
            $preference->email = $data['email'] ?? NotificationEvent::DEFAULT_EMAIL;
        }

        if ($request->has('channel_ids')) {
            $preference->channel_ids = $data['channel_ids'] ?? null;
        }

        $preference->save();

        return ApiResponse::success(['preference' => $preference], 'Notification preference saved.');
    }
}
