<?php

namespace App\Models\Workflows;

use App\Enums\Workflows\PageAccess;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Workflows\WorkflowPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A page built on top of a workflow: a form for its inputs and a place for
 * its result, so people can use the workflow without opening the canvas.
 *
 * `content` is the draft the builder edits; `published_content` is what
 * visitors see, copied from the draft on publish: the same split workflows
 * have, so an edit in progress never shows on a live page. Both are always
 * in the canonical shape `PageSchema::normalize()` returns.
 *
 * `public_token` is the page's link. It exists for every page, but only
 * answers when `access` is public; rotating it breaks the old link.
 */
#[Fillable(['workspace_id', 'workflow_id', 'created_by', 'name', 'description', 'content', 'access', 'is_enabled', 'max_runs_per_day', 'share_title', 'share_description', 'share_image_url', 'variant_content', 'variant_split'])]
#[Hidden(['password'])]
class WorkflowPage extends Model
{
    /** @use HasFactory<WorkflowPageFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'access' => 'workspace',
        'is_enabled' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $page): void {
            $page->public_token ??= self::newToken();
        });

        // Whatever changes a draft (the builder, a restore, version B) moves
        // the revision on; see `WorkflowPages::update()`.
        static::updating(function (self $page): void {
            if ($page->isDirty(['content', 'variant_content'])) {
                $page->draft_revision = (int) $page->getOriginal('draft_revision') + 1;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'published_content' => 'array',
            'variant_content' => 'array',
            'published_variant_content' => 'array',
            'variant_split' => 'integer',
            'draft_revision' => 'integer',
            'published_at' => 'datetime',
            'access' => PageAccess::class,
            'password' => 'hashed',
            'is_enabled' => 'boolean',
            'max_runs_per_day' => 'integer',
        ];
    }

    public static function newToken(): string
    {
        return Str::random(32);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(WorkflowPageSubmission::class);
    }

    public function isPublished(): bool
    {
        return $this->published_content !== null;
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->published_content === null
            || json_encode($this->content) !== json_encode($this->published_content)
            || json_encode($this->variant_content) !== json_encode($this->published_variant_content);
    }

    /**
     * Whether an A/B test is live: visitors are split between the published
     * page and its published version B.
     */
    public function isTestingVariant(): bool
    {
        return $this->published_variant_content !== null;
    }

    /**
     * The content of version `$variant`: the live one, or the draft.
     *
     * @return array{blocks: array<int, array<string, mixed>>, theme: array<string, mixed>}|null
     */
    public function contentFor(string $variant, bool $published = true): ?array
    {
        if ($variant === 'b') {
            return $published ? $this->published_variant_content : $this->variant_content;
        }

        return $published ? $this->published_content : $this->content;
    }

    public function events(): HasMany
    {
        return $this->hasMany(WorkflowPageEvent::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowPageVersion::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WorkflowPageComment::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(WorkflowPageGeneration::class);
    }

    /**
     * Whether the page answers at its public link right now.
     */
    public function isPubliclyAvailable(): bool
    {
        return $this->is_enabled && $this->access->isPublic() && $this->isPublished();
    }
}
