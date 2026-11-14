<?php

namespace App\Services\Changelog;

use App\Enums\Changelog\ChangelogStatus;
use App\Enums\Changelog\ChangelogVisibility;
use App\Models\Changelog\ChangelogEntry;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\Broadcasts\BroadcastAudience;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Writing, publishing, scheduling and archiving changelog entries. Every
 * change is audited. Saving never shows anything to readers: an entry is seen
 * only once it is published (or its scheduled time has passed and
 * `changelog:publish-due` has run).
 */
class ChangelogService
{
    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(SuperAdmin $actor, array $data): ChangelogEntry
    {
        return DB::transaction(function () use ($actor, $data): ChangelogEntry {
            $entry = new ChangelogEntry($this->prepared($data));
            $entry->slug = $data['slug'] ?? ChangelogEntry::uniqueSlug((string) $data['title']);
            $entry->status = ChangelogStatus::Draft;
            $entry->created_by = $actor->id;
            $entry->updated_by = $actor->id;
            $entry->save();

            $this->audit->record($actor, 'changelog.created', $entry, after: $this->snapshot($entry));

            return $entry;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SuperAdmin $actor, ChangelogEntry $entry, array $data): ChangelogEntry
    {
        return DB::transaction(function () use ($actor, $entry, $data): ChangelogEntry {
            $before = $this->snapshot($entry);

            $entry->fill($this->prepared($data, $entry));
            $entry->updated_by = $actor->id;
            $entry->save();

            $this->audit->recordChanges($actor, 'changelog.updated', $entry, $before);

            return $entry;
        });
    }

    public function publish(SuperAdmin $actor, ChangelogEntry $entry): ChangelogEntry
    {
        if ($entry->status === ChangelogStatus::Published) {
            return $entry;
        }

        return $this->transition($actor, $entry, 'changelog.published', function (ChangelogEntry $entry): void {
            $entry->status = ChangelogStatus::Published;
            $entry->published_at = now();
            $entry->scheduled_for = null;
        });
    }

    public function schedule(SuperAdmin $actor, ChangelogEntry $entry, Carbon $when): ChangelogEntry
    {
        abort_if($entry->status === ChangelogStatus::Published, 409, 'This entry is already published. Take it down before scheduling it.');
        abort_if($when->isPast(), 422, 'Pick a time in the future.');

        return $this->transition($actor, $entry, 'changelog.scheduled', function (ChangelogEntry $entry) use ($when): void {
            $entry->status = ChangelogStatus::Scheduled;
            $entry->scheduled_for = $when;
            $entry->published_at = null;
        });
    }

    public function unpublish(SuperAdmin $actor, ChangelogEntry $entry): ChangelogEntry
    {
        if (in_array($entry->status, [ChangelogStatus::Draft], true)) {
            return $entry;
        }

        return $this->transition($actor, $entry, 'changelog.unpublished', function (ChangelogEntry $entry): void {
            $entry->status = ChangelogStatus::Draft;
            $entry->published_at = null;
            $entry->scheduled_for = null;
        });
    }

    public function archive(SuperAdmin $actor, ChangelogEntry $entry): ChangelogEntry
    {
        if ($entry->status === ChangelogStatus::Archived) {
            return $entry;
        }

        return $this->transition($actor, $entry, 'changelog.archived', function (ChangelogEntry $entry): void {
            $entry->status = ChangelogStatus::Archived;
            $entry->scheduled_for = null;
        });
    }

    public function delete(SuperAdmin $actor, ChangelogEntry $entry): void
    {
        DB::transaction(function () use ($actor, $entry): void {
            $this->audit->record($actor, 'changelog.deleted', $entry, $this->snapshot($entry));

            $entry->delete();
        });
    }

    /**
     * Publishes every scheduled entry whose time has come, stamping it with
     * the time it was due so the timeline shows when it was meant to go out.
     * Returns how many went out.
     */
    public function publishDue(): int
    {
        $published = 0;

        ChangelogEntry::query()->due()->orderBy('scheduled_for')->each(function (ChangelogEntry $entry) use (&$published): void {
            DB::transaction(function () use ($entry): void {
                $entry->forceFill([
                    'status' => ChangelogStatus::Published,
                    'published_at' => $entry->scheduled_for,
                    'scheduled_for' => null,
                ])->save();

                $this->audit->record(null, 'changelog.published', $entry, ['status' => 'scheduled'], ['status' => 'published'], actorLabel: 'scheduler');
            });

            $published++;
        });

        return $published;
    }

    /**
     * @param  callable(ChangelogEntry): void  $apply
     */
    private function transition(SuperAdmin $actor, ChangelogEntry $entry, string $action, callable $apply): ChangelogEntry
    {
        return DB::transaction(function () use ($actor, $entry, $action, $apply): ChangelogEntry {
            $before = ['status' => $entry->status->value, 'published_at' => $entry->published_at, 'scheduled_for' => $entry->scheduled_for];

            $apply($entry);
            $entry->updated_by = $actor->id;
            $entry->save();

            $this->audit->record($actor, $action, $entry, $before, ['status' => $entry->status->value, 'published_at' => $entry->published_at, 'scheduled_for' => $entry->scheduled_for]);

            return $entry;
        });
    }

    /**
     * Settles what the form rules can't see on their own: a "selected
     * audience" entry needs an audience, and any other visibility drops it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepared(array $data, ?ChangelogEntry $existing = null): array
    {
        $visibility = ChangelogVisibility::from((string) ($data['visibility'] ?? $existing?->visibility->value ?? 'public'));
        $audience = array_key_exists('audience', $data) ? $data['audience'] : $existing?->audience;

        if ($visibility === ChangelogVisibility::Audience) {
            $normalised = app(BroadcastAudience::class)->normalize($audience, 'all');

            abort_if($normalised === app(BroadcastAudience::class)->normalize(null, 'all'), 422, 'Choose who this entry is for, or make it visible to all signed-in members.');

            $data['audience'] = $normalised;
        } else {
            $data['audience'] = null;
        }

        if (array_key_exists('areas', $data)) {
            $data['areas'] = array_values(array_unique($data['areas'] ?? []));
        }

        unset($data['slug']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ChangelogEntry $entry): array
    {
        return $entry->only(['slug', 'version', 'title', 'summary', 'body', 'category', 'areas', 'visibility', 'audience', 'is_pinned', 'is_major', 'status', 'published_at', 'scheduled_for']);
    }
}
