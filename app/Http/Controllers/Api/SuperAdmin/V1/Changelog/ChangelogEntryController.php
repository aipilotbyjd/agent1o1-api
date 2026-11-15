<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Changelog;

use App\Enums\Changelog\ChangelogCategory;
use App\Enums\Changelog\ChangelogStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Changelog\ChangelogEntryRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Changelog\PreviewChangelogRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Changelog\ScheduleChangelogEntryRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Changelog\ChangelogEntryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Changelog\ChangelogEntry;
use App\Services\Changelog\ChangelogService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Release notes. Saving never shows anything: an entry reaches readers only
 * once it is published.
 */
class ChangelogEntryController extends Controller
{
    public function __construct(private readonly ChangelogService $changelog) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(ChangelogStatus::class)],
            'category' => ['nullable', Rule::enum(ChangelogCategory::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $entries = ChangelogEntry::query()
            ->withCount('reads')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')->toString()))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.addcslashes($request->string('q')->trim()->toString(), '\\%_').'%';

                $query->where(fn ($match) => $match->where('title', 'like', $term)->orWhere('version', 'like', $term));
            })
            ->latest()
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated(ChangelogEntryResource::collection($entries));
    }

    public function store(ChangelogEntryRequest $request)
    {
        $entry = $this->changelog->create($request->user('super-admin'), $request->validated());

        return ApiResponse::created((new ChangelogEntryResource($entry))->resolve($request), 'Entry saved as a draft.');
    }

    public function show(Request $request, ChangelogEntry $entry)
    {
        $entry->loadCount('reads');

        return ApiResponse::success((new ChangelogEntryResource($entry))->resolve($request));
    }

    public function update(ChangelogEntryRequest $request, ChangelogEntry $entry)
    {
        $entry = $this->changelog->update($request->user('super-admin'), $entry, $request->validated());

        return ApiResponse::success((new ChangelogEntryResource($entry))->resolve($request), 'Entry updated.');
    }

    public function destroy(Request $request, ChangelogEntry $entry)
    {
        $this->changelog->delete($request->user('super-admin'), $entry);

        return ApiResponse::success(null, 'Entry deleted.');
    }

    public function publish(Request $request, ChangelogEntry $entry)
    {
        $entry = $this->changelog->publish($request->user('super-admin'), $entry);

        return ApiResponse::success((new ChangelogEntryResource($entry))->resolve($request), 'Entry published.');
    }

    public function schedule(ScheduleChangelogEntryRequest $request, ChangelogEntry $entry)
    {
        $entry = $this->changelog->schedule($request->user('super-admin'), $entry, Carbon::parse($request->validated('scheduled_for')));

        return ApiResponse::success((new ChangelogEntryResource($entry))->resolve($request), 'Entry scheduled.');
    }

    public function unpublish(Request $request, ChangelogEntry $entry)
    {
        $entry = $this->changelog->unpublish($request->user('super-admin'), $entry);

        return ApiResponse::success((new ChangelogEntryResource($entry))->resolve($request), 'Entry moved back to drafts.');
    }

    public function archive(Request $request, ChangelogEntry $entry)
    {
        $entry = $this->changelog->archive($request->user('super-admin'), $entry);

        return ApiResponse::success((new ChangelogEntryResource($entry))->resolve($request), 'Entry archived.');
    }

    /**
     * The sanitised HTML a body renders to, so the editor preview shows
     * exactly what readers will see.
     */
    public function preview(PreviewChangelogRequest $request)
    {
        return ApiResponse::success(['html' => ChangelogEntry::renderMarkdown($request->validated('body'))]);
    }
}
