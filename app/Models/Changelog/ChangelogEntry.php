<?php

namespace App\Models\Changelog;

use App\Enums\Changelog\ChangelogCategory;
use App\Enums\Changelog\ChangelogStatus;
use App\Enums\Changelog\ChangelogVisibility;
use App\Models\SuperAdmin\SuperAdmin;
use Database\Factories\Changelog\ChangelogEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One release note. Readers only ever see `published` entries whose
 * `published_at` has arrived; drafts, scheduled and archived entries are
 * invisible to every reader endpoint.
 */
#[Fillable([
    'slug', 'version', 'title', 'summary', 'body', 'cover_image_url', 'category', 'areas',
    'visibility', 'audience', 'is_pinned', 'is_major', 'status', 'published_at', 'scheduled_for',
    'created_by', 'updated_by',
])]
class ChangelogEntry extends Model
{
    /** @use HasFactory<ChangelogEntryFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'category' => 'feature',
        'visibility' => 'public',
        'status' => 'draft',
        'is_pinned' => false,
        'is_major' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ChangelogCategory::class,
            'visibility' => ChangelogVisibility::class,
            'status' => ChangelogStatus::class,
            'areas' => 'array',
            'audience' => 'array',
            'is_pinned' => 'boolean',
            'is_major' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_for' => 'datetime',
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
     * @return HasMany<ChangelogRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(ChangelogRead::class, 'changelog_entry_id');
    }

    /**
     * Published and already out: what readers can see.
     *
     * @param  Builder<ChangelogEntry>  $query
     */
    public function scopeReleased(Builder $query): void
    {
        $query->where('status', ChangelogStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scheduled entries whose time has come.
     *
     * @param  Builder<ChangelogEntry>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->where('status', ChangelogStatus::Scheduled)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now());
    }

    /**
     * Entries anyone may read, signed in or not.
     *
     * @param  Builder<ChangelogEntry>  $query
     */
    public function scopePublicVisibility(Builder $query): void
    {
        $query->where('visibility', ChangelogVisibility::Public);
    }

    public function isReleased(): bool
    {
        return $this->status === ChangelogStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    /**
     * A slug from `$title` that no other entry uses.
     */
    public static function uniqueSlug(string $title, ?string $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'update';
        $slug = $base;
        $suffix = 2;

        while (self::query()->where('slug', $slug)->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Markdown body as safe HTML: raw HTML is dropped and only http(s),
     * mailto and relative links survive.
     */
    public function bodyHtml(): string
    {
        return self::renderMarkdown($this->body);
    }

    public static function renderMarkdown(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
