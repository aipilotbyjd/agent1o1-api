<?php

namespace App\Services\Broadcasts;

use App\Enums\Broadcasts\AnnouncementType;
use App\Events\Broadcasts\AnnouncementsChanged;
use App\Models\Broadcasts\Announcement;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creating, publishing and ending in-app announcements. Every change is
 * audited and pushes a content-free nudge to open apps (`AnnouncementsChanged`).
 *
 * A maintenance notice needs a full window, and a write-blocking one is held
 * to a short one (`MAX_BLOCKING_HOURS`) so a forgotten notice can't leave the
 * product read-only for good.
 */
class AnnouncementService
{
    public const MAX_BLOCKING_HOURS = 24;

    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(SuperAdmin $actor, array $data): Announcement
    {
        return DB::transaction(function () use ($actor, $data): Announcement {
            $announcement = new Announcement($this->prepared($data));
            $announcement->created_by = $actor->id;
            $announcement->save();

            $this->audit->record($actor, 'announcements.created', $announcement, after: $this->snapshot($announcement));

            return $announcement;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SuperAdmin $actor, Announcement $announcement, array $data): Announcement
    {
        return DB::transaction(function () use ($actor, $announcement, $data): Announcement {
            $before = $this->snapshot($announcement);

            $announcement->fill($this->prepared($data, $announcement))->save();

            $this->audit->recordChanges($actor, 'announcements.updated', $announcement, $before);
            $this->push('updated', $announcement);

            return $announcement;
        });
    }

    public function publish(SuperAdmin $actor, Announcement $announcement): Announcement
    {
        if ($announcement->published_at !== null) {
            return $announcement;
        }

        abort_if(
            $announcement->ends_at !== null && $announcement->ends_at->isPast(),
            409,
            'This announcement has already ended. Move its end date first.',
        );

        DB::transaction(function () use ($actor, $announcement): void {
            $announcement->forceFill(['published_at' => now()])->save();

            $this->audit->record($actor, 'announcements.published', $announcement, ['published' => false], ['published' => true]);
        });

        $this->push('published', $announcement);

        return $announcement;
    }

    public function unpublish(SuperAdmin $actor, Announcement $announcement): Announcement
    {
        if ($announcement->published_at === null) {
            return $announcement;
        }

        DB::transaction(function () use ($actor, $announcement): void {
            $announcement->forceFill(['published_at' => null])->save();

            $this->audit->record($actor, 'announcements.unpublished', $announcement, ['published' => true], ['published' => false]);
        });

        $this->push('unpublished', $announcement);

        return $announcement;
    }

    public function delete(SuperAdmin $actor, Announcement $announcement): void
    {
        DB::transaction(function () use ($actor, $announcement): void {
            $this->audit->record($actor, 'announcements.deleted', $announcement, $this->snapshot($announcement));

            $announcement->delete();
        });

        $this->push('deleted', $announcement);
    }

    /**
     * Rejects combinations the form rules can't see (they only see the fields
     * sent), then settles what follows from the type: a plain banner never
     * blocks writes, and the audience is stored in its normalised shape.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepared(array $data, ?Announcement $existing = null): array
    {
        $type = AnnouncementType::from((string) ($data['type'] ?? $existing?->type->value ?? 'banner'));
        $startsAt = array_key_exists('starts_at', $data) ? $data['starts_at'] : $existing?->starts_at;
        $endsAt = array_key_exists('ends_at', $data) ? $data['ends_at'] : $existing?->ends_at;
        $blocks = (bool) ($data['blocks_writes'] ?? $existing?->blocks_writes ?? false);

        if ($type === AnnouncementType::Maintenance) {
            abort_if($startsAt === null || $endsAt === null, 422, 'A maintenance notice needs a start and an end time.');
        } else {
            $blocks = false;
        }

        if ($blocks) {
            $hours = abs(Carbon::parse($endsAt)->diffInHours(Carbon::parse($startsAt)));

            abort_if($hours > self::MAX_BLOCKING_HOURS, 422, 'A window that blocks writes can last at most '.self::MAX_BLOCKING_HOURS.' hours.');
        }

        if (array_key_exists('audience', $data)) {
            $data['audience'] = app(BroadcastAudience::class)->normalize($data['audience'], 'all');
        }

        return [...$data, 'blocks_writes' => $blocks];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Announcement $announcement): array
    {
        return $announcement->only(['type', 'severity', 'title', 'body', 'cta_label', 'cta_url', 'dismissible', 'blocks_writes', 'audience', 'starts_at', 'ends_at', 'published_at']);
    }

    private function push(string $reason, Announcement $announcement): void
    {
        if ($announcement->published_at !== null || $reason === 'unpublished' || $reason === 'deleted') {
            AnnouncementsChanged::dispatch($reason);
        }
    }
}
