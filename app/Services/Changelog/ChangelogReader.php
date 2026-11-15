<?php

namespace App\Services\Changelog;

use App\Enums\Changelog\ChangelogVisibility;
use App\Models\Changelog\ChangelogEntry;
use App\Models\Changelog\ChangelogRead;
use App\Models\User;
use App\Services\Broadcasts\BroadcastAudience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * What a reader may see of the changelog, and what they have read. Visitors
 * who aren't signed in only get `public` entries; a member also gets
 * `authenticated` ones and those whose audience they belong to.
 */
class ChangelogReader
{
    public function __construct(private readonly BroadcastAudience $audience) {}

    /**
     * @return Builder<ChangelogEntry>
     */
    public function publicEntries(): Builder
    {
        return ChangelogEntry::query()->released()->publicVisibility();
    }

    /**
     * @return Builder<ChangelogEntry>
     */
    public function entriesFor(User $user): Builder
    {
        $audienceIds = ChangelogEntry::query()
            ->released()
            ->where('visibility', ChangelogVisibility::Audience)
            ->get(['id', 'audience'])
            ->filter(fn (ChangelogEntry $entry): bool => $this->audience->matches($user, $entry->audience))
            ->pluck('id')
            ->all();

        return ChangelogEntry::query()
            ->released()
            ->where(fn (Builder $query) => $query
                ->whereIn('visibility', [ChangelogVisibility::Public, ChangelogVisibility::Authenticated])
                ->orWhereIn('id', $audienceIds));
    }

    /**
     * Applies the list filters: `category`, `area`, `q`, `pinned`.
     *
     * @param  Builder<ChangelogEntry>  $query
     * @return Builder<ChangelogEntry>
     */
    public function filtered(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('category'), fn (Builder $inner) => $inner->where('category', $request->string('category')->toString()))
            ->when($request->filled('area'), fn (Builder $inner) => $inner->whereJsonContains('areas', $request->string('area')->toString()))
            ->when($request->boolean('pinned'), fn (Builder $inner) => $inner->where('is_pinned', true))
            ->when($request->filled('q'), function (Builder $inner) use ($request): void {
                $term = '%'.addcslashes($request->string('q')->trim()->toString(), '\\%_').'%';

                $inner->where(fn (Builder $match) => $match
                    ->where('title', 'like', $term)
                    ->orWhere('summary', 'like', $term)
                    ->orWhere('body', 'like', $term));
            })
            ->orderByDesc('published_at')
            ->orderBy('id');
    }

    /**
     * @param  Builder<ChangelogEntry>  $visible
     */
    public function unreadCount(User $user, Builder $visible): int
    {
        return (clone $visible)
            ->whereNotIn('id', ChangelogRead::query()->where('user_id', $user->id)->select('changelog_entry_id'))
            ->count();
    }

    public function markRead(User $user, ChangelogEntry $entry): void
    {
        ChangelogRead::query()->firstOrCreate(
            ['changelog_entry_id' => $entry->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );
    }

    /**
     * @param  Builder<ChangelogEntry>  $visible
     */
    public function markAllRead(User $user, Builder $visible): int
    {
        $unread = (clone $visible)
            ->whereNotIn('id', ChangelogRead::query()->where('user_id', $user->id)->select('changelog_entry_id'))
            ->pluck('id');

        foreach ($unread as $id) {
            ChangelogRead::query()->firstOrCreate(['changelog_entry_id' => $id, 'user_id' => $user->id], ['read_at' => now()]);
        }

        return $unread->count();
    }

    /**
     * @param  list<string>  $entryIds
     * @return list<string>
     */
    public function readIds(User $user, array $entryIds): array
    {
        return ChangelogRead::query()
            ->where('user_id', $user->id)
            ->whereIn('changelog_entry_id', $entryIds)
            ->pluck('changelog_entry_id')
            ->all();
    }
}
