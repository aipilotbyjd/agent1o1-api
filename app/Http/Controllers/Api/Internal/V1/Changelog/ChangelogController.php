<?php

namespace App\Http\Controllers\Api\Internal\V1\Changelog;

use App\Enums\Changelog\ChangelogCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Changelog\ChangelogEntryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Changelog\ChangelogEntry;
use App\Models\User;
use App\Services\Changelog\ChangelogReader;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The changelog as a signed-in member reads it: public and members-only
 * entries plus those aimed at an audience they belong to, with what they have
 * already read.
 */
class ChangelogController extends Controller
{
    public function __construct(private readonly ChangelogReader $reader) {}

    public function index(Request $request)
    {
        $request->validate([
            'category' => ['nullable', Rule::enum(ChangelogCategory::class)],
            'area' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:100'],
            'pinned' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        $entries = $this->reader->filtered($this->reader->entriesFor($user), $request)->paginate($this->perPage($request, 20, 50));

        $this->flagRead($user, $entries->getCollection());

        return ApiResponse::paginated(ChangelogEntryResource::collection($entries));
    }

    public function show(Request $request, string $slug)
    {
        $entry = $this->reader->entriesFor($request->user())->where('slug', $slug)->firstOrFail();

        $this->flagRead($request->user(), collect([$entry]));

        return ApiResponse::success((new ChangelogEntryResource($entry))->resolve($request));
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();

        return ApiResponse::success(['unread' => $this->reader->unreadCount($user, $this->reader->entriesFor($user))]);
    }

    /**
     * The newest major entry the member hasn't read, for the "what's new" popup.
     */
    public function latestMajor(Request $request)
    {
        $user = $request->user();

        $entry = $this->reader->entriesFor($user)
            ->where('is_major', true)
            ->whereNotIn('id', $user->changelogReads()->select('changelog_entry_id'))
            ->orderByDesc('published_at')
            ->first();

        if ($entry === null) {
            return ApiResponse::success(['entry' => null]);
        }

        $entry->setAttribute('is_read', false);

        return ApiResponse::success(['entry' => (new ChangelogEntryResource($entry))->resolve($request)]);
    }

    public function markRead(Request $request, string $slug)
    {
        $entry = $this->reader->entriesFor($request->user())->where('slug', $slug)->firstOrFail();

        $this->reader->markRead($request->user(), $entry);

        return ApiResponse::success(null, 'Marked as read.');
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();

        $count = $this->reader->markAllRead($user, $this->reader->entriesFor($user));

        return ApiResponse::success(['marked' => $count], 'All caught up.');
    }

    /**
     * @param  iterable<int, ChangelogEntry>  $entries
     */
    private function flagRead(User $user, iterable $entries): void
    {
        $ids = collect($entries)->pluck('id')->all();
        $read = array_flip($this->reader->readIds($user, $ids));

        foreach ($entries as $entry) {
            $entry->setAttribute('is_read', isset($read[$entry->id]));
        }
    }
}
