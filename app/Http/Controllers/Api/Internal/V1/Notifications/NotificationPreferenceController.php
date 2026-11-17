<?php

namespace App\Http\Controllers\Api\Internal\V1\Notifications;

use App\Enums\Notifications\NotificationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Notifications\UpdateNotificationPreferencesRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Notifications\NotificationPreference;
use App\Models\Workspaces\Workspace;
use App\Services\Notifications\PreferenceWriter;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A member's notification choices in one workspace: per event, or per
 * category (`category:<name>`). Account-level events are set on the
 * account (`AccountNotificationPreferenceController`).
 */
class NotificationPreferenceController extends Controller
{
    public function __construct(private readonly PreferenceWriter $writer) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        return ApiResponse::success(['preferences' => $this->preferencesFor($request, $workspace)]);
    }

    /**
     * One change, as the settings page has always sent it.
     */
    public function upsert(Request $request, Workspace $workspace): JsonResponse
    {
        $data = $request->validate([
            'event_key' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! UpdateNotificationPreferencesRequest::acceptsKey($value, NotificationScope::Workspace)) {
                    $fail('This notification setting can\'t be changed here.');
                }
            }],
            'in_app' => ['sometimes', 'boolean'],
            'email' => ['sometimes', 'boolean'],
            'email_digest' => ['sometimes', 'boolean'],
        ]);

        $this->writer->applyToWorkspace($request->user(), $workspace, [[
            'key' => $data['event_key'],
            ...array_intersect_key($data, array_flip(['in_app', 'email', 'email_digest'])),
        ]]);

        $preference = NotificationPreference::query()
            ->whereBelongsTo($workspace)
            ->whereBelongsTo($request->user())
            ->where('event_key', $data['event_key'])
            ->firstOrFail();

        return ApiResponse::success(['preference' => $preference], 'Notification preference saved.');
    }

    /**
     * Several changes at once: a whole category, or every row of the
     * settings page.
     */
    public function bulk(UpdateNotificationPreferencesRequest $request, Workspace $workspace): JsonResponse
    {
        $this->writer->applyToWorkspace($request->user(), $workspace, $request->changes());

        return ApiResponse::success(['preferences' => $this->preferencesFor($request, $workspace)], 'Notification preferences saved.');
    }

    /**
     * @return Collection<int, NotificationPreference>
     */
    private function preferencesFor(Request $request, Workspace $workspace): Collection
    {
        return NotificationPreference::query()
            ->whereBelongsTo($workspace)
            ->whereBelongsTo($request->user())
            ->get();
    }
}
