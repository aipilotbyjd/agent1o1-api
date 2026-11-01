<?php

namespace App\Models\Broadcasts;

use App\Enums\Broadcasts\AnnouncementType;
use App\Enums\Notifications\AlertSeverity;
use App\Models\SuperAdmin\SuperAdmin;
use Database\Factories\Broadcasts\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * A banner or maintenance notice shown inside the app. It is visible only
 * while published and between `starts_at` and `ends_at` (either may be open).
 * The list of live announcements is cached briefly; any change bumps the cache
 * version, so a publish or an edit shows up at once.
 */
#[Fillable([
    'type', 'severity', 'title', 'body', 'cta_label', 'cta_url', 'dismissible', 'blocks_writes',
    'audience', 'starts_at', 'ends_at', 'published_at', 'created_by',
])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_LIVE = 'live';

    public const STATUS_ENDED = 'ended';

    private const CACHE_VERSION_KEY = 'announcements:version';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'banner',
        'severity' => 'info',
        'dismissible' => true,
        'blocks_writes' => false,
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'severity' => AlertSeverity::class,
            'dismissible' => 'boolean',
            'blocks_writes' => 'boolean',
            'audience' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SuperAdmin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'created_by');
    }

    /**
     * @return HasMany<AnnouncementDismissal, $this>
     */
    public function dismissals(): HasMany
    {
        return $this->hasMany(AnnouncementDismissal::class);
    }

    /**
     * What members can see right now: published and not yet over, and either
     * started or a maintenance notice (those are shown ahead of their window,
     * which is the point of announcing one).
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $now = now();

        $query->whereNotNull('published_at')
            ->where(fn (Builder $inner) => $inner->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->where(fn (Builder $inner) => $inner
                ->where('type', AnnouncementType::Maintenance)
                ->orWhereNull('starts_at')
                ->orWhere('starts_at', '<=', $now));
    }

    /**
     * Where the announcement is in its life, from its dates alone.
     */
    public function status(): string
    {
        if ($this->published_at === null) {
            return self::STATUS_DRAFT;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return self::STATUS_ENDED;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return self::STATUS_SCHEDULED;
        }

        return self::STATUS_LIVE;
    }

    public function isLive(): bool
    {
        return $this->status() === self::STATUS_LIVE;
    }

    /**
     * Published and inside its own window: started and not over. Unlike
     * `scopeLive()` this leaves out a maintenance notice that is only announced.
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeInWindow(Builder $query): void
    {
        $now = now();

        $query->whereNotNull('published_at')
            ->where(fn (Builder $inner) => $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $inner) => $inner->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }

    /**
     * `scopeLive()` for a model already in hand, so a cached list is re-checked
     * against the clock on every use.
     */
    public function isVisible(): bool
    {
        return $this->published_at !== null
            && ($this->ends_at === null || $this->ends_at->isFuture())
            && ($this->type === AnnouncementType::Maintenance || $this->starts_at === null || $this->starts_at->isPast());
    }

    /**
     * Whether `now` falls inside the notice's own window (started, not over).
     */
    public function isInWindow(): bool
    {
        return $this->published_at !== null
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * Whether this notice is a maintenance window that stops writes while open.
     */
    public function blocksWrites(): bool
    {
        return $this->type === AnnouncementType::Maintenance && $this->blocks_writes;
    }

    /**
     * Every live announcement, newest first, cached for a minute per version.
     * Callers still apply the audience and the member's dismissals.
     *
     * The cache holds plain attribute arrays and the models are rebuilt from
     * them: `cache.serializable_classes` is off, so a cached model would come
     * back as an incomplete class.
     *
     * @return Collection<int, Announcement>
     */
    public static function liveCached(): Collection
    {
        $rows = Cache::remember(
            'announcements:live:'.self::cacheVersion(),
            now()->addMinute(),
            fn (): array => self::query()->live()->latest('published_at')->get()
                ->map(fn (self $announcement): array => $announcement->getAttributes())
                ->all(),
        );

        return self::hydrate($rows);
    }

    /**
     * The write-blocking maintenance notices that haven't ended, cached with
     * the live list. The caller checks which of them is inside its window.
     *
     * @return Collection<int, Announcement>
     */
    public static function blockingCached(): Collection
    {
        return self::liveCached()->filter(fn (Announcement $announcement): bool => $announcement->blocksWrites())->values();
    }

    public static function forgetCache(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, self::cacheVersion() + 1);
    }

    private static function cacheVersion(): int
    {
        return (int) Cache::get(self::CACHE_VERSION_KEY, 1);
    }
}
