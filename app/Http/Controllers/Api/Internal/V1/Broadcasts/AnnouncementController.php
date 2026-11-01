<?php

namespace App\Http\Controllers\Api\Internal\V1\Broadcasts;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Broadcasts\AnnouncementResource;
use App\Http\Responses\ApiResponse;
use App\Models\Broadcasts\Announcement;
use App\Models\Broadcasts\AnnouncementDismissal;
use App\Models\User;
use App\Services\Broadcasts\BroadcastAudience;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The banners and maintenance notices the signed-in member should see. The
 * list of live announcements is cached; what is personal (audience, the
 * member's own dismissals) is worked out per request.
 */
class AnnouncementController extends Controller
{
    public function __construct(private readonly BroadcastAudience $audience) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $visible = $this->visibleTo($user);

        $dismissed = AnnouncementDismissal::query()
            ->where('user_id', $user->id)
            ->whereIn('announcement_id', $visible->pluck('id'))
            ->pluck('announcement_id');

        $announcements = $visible->reject(fn (Announcement $announcement): bool => $announcement->dismissible && $dismissed->contains($announcement->id))->values();

        return ApiResponse::success([
            'announcements' => AnnouncementResource::collection($announcements),
            'maintenance_blocking_writes' => $announcements->contains(fn (Announcement $announcement): bool => $announcement->blocksWrites() && $announcement->isInWindow()),
        ]);
    }

    public function dismiss(Request $request, string $announcement)
    {
        $user = $request->user();

        $target = $this->visibleTo($user)->firstWhere('id', $announcement);

        abort_if($target === null, 404, 'This announcement is no longer available.');
        abort_unless($target->dismissible, 422, 'This announcement cannot be dismissed.');

        AnnouncementDismissal::query()->firstOrCreate(
            ['announcement_id' => $target->id, 'user_id' => $user->id],
            ['dismissed_at' => now()],
        );

        return ApiResponse::success(null, 'Announcement dismissed.');
    }

    /**
     * @return Collection<int, Announcement>
     */
    private function visibleTo(User $user): Collection
    {
        return Announcement::liveCached()
            ->filter(fn (Announcement $announcement): bool => $announcement->isVisible() && $this->audience->matches($user, $announcement->audience))
            ->values();
    }
}
