<?php

namespace App\Http\Controllers\Api\Internal\V1\Notifications;

use App\Enums\Notifications\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Notifications\ArchiveNotificationsRequest;
use App\Http\Requests\Api\Internal\V1\Notifications\MarkNotificationsReadRequest;
use App\Http\Resources\Api\Internal\V1\Notifications\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Notifications\InboxNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The member's inbox: every notification sent to them, across workspaces.
 *
 * Filters (`GET /notifications`): `status` (`inbox`, the default, which
 * hides archived ones; `unread`; `archived`; `all`), `category`,
 * `workspace_id` (`none` for account-level ones) and `needs_action`.
 * `unread=1` is still accepted for the older bell.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(['inbox', 'unread', 'archived', 'all'])],
            'category' => ['sometimes', Rule::enum(NotificationCategory::class)],
            'workspace_id' => ['sometimes', 'string'],
            'needs_action' => ['sometimes', 'boolean'],
            'unread' => ['sometimes', 'boolean'],
        ]);

        $status = $filters['status'] ?? ($request->boolean('unread') ? 'unread' : 'inbox');

        $notifications = $this->inbox($request->user())
            ->when($status === 'inbox', fn (Builder $query) => $query->whereNull('archived_at'))
            ->when($status === 'unread', fn (Builder $query) => $query->whereNull('archived_at')->whereNull('read_at'))
            ->when($status === 'archived', fn (Builder $query) => $query->whereNotNull('archived_at'))
            ->when(isset($filters['category']), fn (Builder $query) => $query->where('category', $filters['category']))
            ->when(isset($filters['workspace_id']), fn (Builder $query) => $filters['workspace_id'] === 'none'
                ? $query->whereNull('workspace_id')
                : $query->where('workspace_id', $filters['workspace_id']))
            ->when($request->boolean('needs_action'), fn (Builder $query) => $query->needingAction())
            ->paginate($this->perPage($request));

        return ApiResponse::paginated(NotificationResource::collection($notifications));
    }

    /**
     * Unread counts for the bell: in total, needing action, and per category
     * and workspace (`none` for account-level ones). Archived notifications
     * aren't counted.
     */
    public function summary(Request $request): JsonResponse
    {
        $unread = fn (): Builder => InboxNotification::query()
            ->where('notifiable_type', $request->user()->getMorphClass())
            ->where('notifiable_id', $request->user()->getKey())
            ->whereNull('read_at')
            ->whereNull('archived_at');

        return ApiResponse::success([
            'unread' => $unread()->count(),
            'needs_action' => $unread()->needingAction()->count(),
            'by_category' => (object) $unread()->whereNotNull('category')
                ->groupBy('category')
                ->selectRaw('category, count(*) as aggregate')
                ->pluck('aggregate', 'category')
                ->map(fn (mixed $count): int => (int) $count)
                ->all(),
            'by_workspace' => (object) $unread()
                ->groupBy('workspace_id')
                ->selectRaw('workspace_id, count(*) as aggregate')
                ->get()
                ->mapWithKeys(fn (InboxNotification $row): array => [$row->workspace_id ?? 'none' => (int) $row->getAttribute('aggregate')])
                ->all(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'unread' => $request->user()->unreadNotifications()->whereNull('archived_at')->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $databaseNotification = $this->inbox($request->user())->findOrFail($notification);
        $databaseNotification->markAsRead();

        return ApiResponse::success(['notification' => NotificationResource::make($databaseNotification)], 'Notification marked read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success(message: 'All notifications marked read.');
    }

    public function markManyRead(MarkNotificationsReadRequest $request): JsonResponse
    {
        $updated = $this->inbox($request->user())
            ->whereNull('read_at')
            ->when(! $request->boolean('all'), fn (Builder $query) => $query->whereKey($request->validated('ids', [])))
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->validated('category')))
            ->when($request->filled('workspace_id'), fn (Builder $query) => $query->where('workspace_id', $request->validated('workspace_id')))
            ->toBase()
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return ApiResponse::success(['updated' => $updated], 'Notifications marked read.');
    }

    public function archive(ArchiveNotificationsRequest $request): JsonResponse
    {
        $updated = $this->inbox($request->user())
            ->whereKey($request->validated('ids'))
            ->whereNull('archived_at')
            ->toBase()
            ->update(['archived_at' => now(), 'read_at' => now(), 'updated_at' => now()]);

        return ApiResponse::success(['updated' => $updated], 'Notifications archived.');
    }

    public function unarchive(ArchiveNotificationsRequest $request): JsonResponse
    {
        $updated = $this->inbox($request->user())
            ->whereKey($request->validated('ids'))
            ->whereNotNull('archived_at')
            ->toBase()
            ->update(['archived_at' => null, 'updated_at' => now()]);

        return ApiResponse::success(['updated' => $updated], 'Notifications moved back to the inbox.');
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $this->inbox($request->user())->findOrFail($notification)->delete();

        return ApiResponse::success(message: 'Notification deleted.');
    }

    /**
     * @return MorphMany<InboxNotification, User>
     */
    private function inbox(User $user): MorphMany
    {
        return $user->notifications();
    }
}
