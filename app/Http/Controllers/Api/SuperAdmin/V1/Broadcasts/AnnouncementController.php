<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Broadcasts;

use App\Enums\Broadcasts\AnnouncementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Broadcasts\AnnouncementRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Broadcasts\AnnouncementResource;
use App\Http\Responses\ApiResponse;
use App\Models\Broadcasts\Announcement;
use App\Services\Broadcasts\AnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Banners and maintenance notices. Saving never shows anything: an
 * announcement reaches members only once it is published.
 */
class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in([Announcement::STATUS_DRAFT, Announcement::STATUS_SCHEDULED, Announcement::STATUS_LIVE, Announcement::STATUS_ENDED])],
            'type' => ['nullable', Rule::enum(AnnouncementType::class)],
        ]);

        $now = now();

        $announcements = Announcement::query()
            ->withCount('dismissals')
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->string('status')->toString() === Announcement::STATUS_DRAFT, fn ($query) => $query->whereNull('published_at'))
            ->when($request->string('status')->toString() === Announcement::STATUS_SCHEDULED, fn ($query) => $query->whereNotNull('published_at')->where('starts_at', '>', $now))
            ->when($request->string('status')->toString() === Announcement::STATUS_LIVE, fn ($query) => $query->inWindow())
            ->when($request->string('status')->toString() === Announcement::STATUS_ENDED, fn ($query) => $query->whereNotNull('published_at')->where('ends_at', '<=', $now))
            ->latest()
            ->orderBy('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        return ApiResponse::paginated(AnnouncementResource::collection($announcements));
    }

    public function store(AnnouncementRequest $request)
    {
        $announcement = $this->announcements->create($request->user('super-admin'), $request->validated());

        return ApiResponse::created((new AnnouncementResource($announcement))->resolve($request), 'Announcement saved as a draft.');
    }

    public function show(Request $request, Announcement $announcement)
    {
        $announcement->loadCount('dismissals');

        return ApiResponse::success((new AnnouncementResource($announcement))->resolve($request));
    }

    public function update(AnnouncementRequest $request, Announcement $announcement)
    {
        $announcement = $this->announcements->update($request->user('super-admin'), $announcement, $request->validated());

        return ApiResponse::success((new AnnouncementResource($announcement))->resolve($request), 'Announcement updated.');
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        $this->announcements->delete($request->user('super-admin'), $announcement);

        return ApiResponse::success(null, 'Announcement deleted.');
    }

    public function publish(Request $request, Announcement $announcement)
    {
        $announcement = $this->announcements->publish($request->user('super-admin'), $announcement);

        return ApiResponse::success((new AnnouncementResource($announcement))->resolve($request), 'Announcement published.');
    }

    public function unpublish(Request $request, Announcement $announcement)
    {
        $announcement = $this->announcements->unpublish($request->user('super-admin'), $announcement);

        return ApiResponse::success((new AnnouncementResource($announcement))->resolve($request), 'Announcement taken down.');
    }
}
