<?php

namespace App\Models\Agents;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * `skill_source_id` and `source_path` are managed by the skill sync services.
 * Linked skills are editable when their source enables two-way sync.
 *
 * `version` is engine-managed, not in `#[Fillable]`, incremented via
 * `forceFill()`/`increment()` whenever `instructions` changes (see
 * `SkillController::update()`), same "don't let the model behind an
 * in-flight session change under it" reasoning as `Agent`.
 */
#[Fillable(['workspace_id', 'created_by', 'name', 'slug', 'description', 'category', 'icon', 'color', 'tags', 'instructions', 'is_shared'])]
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The categories, icons and colors the Skills page offers: mirrors the
     * frontend's `skills.constants.ts`. Generated drafts pick from these.
     *
     * @var list<string>
     */
    public const CATEGORIES = ['General', 'Research', 'Data', 'Communication', 'Automation', 'Development', 'Content'];

    /**
     * @var list<string>
     */
    public const ICONS = ['Puzzle', 'Wrench01', 'Zap', 'Sparkles', 'AiMagic', 'Idea01', 'Tools'];

    /**
     * @var list<string>
     */
    public const COLORS = ['#6366F1', '#7C3AED', '#D97706', '#10A37F', '#EC4899', '#0EA5E9', '#EF4444'];

    /**
     * A skill's description is listed in the system prompt on every turn, so
     * it is kept short; the full text lives in `instructions`.
     */
    public const int DESCRIPTION_MAX_LENGTH = 500;

    public const int INSTRUCTIONS_MAX_LENGTH = 50000;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_shared' => false,
        'version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_shared' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(SkillSource::class, 'skill_source_id');
    }

    /**
     * Whether this skill is linked to a repository.
     */
    public function isSynced(): bool
    {
        return $this->skill_source_id !== null;
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_skill')->withTimestamps();
    }

    /**
     * The skill as the model reads it: its instructions followed by every
     * reference. Shared by `UseSkillTool` and a skill picked for a message,
     * so both hand the model the same text.
     */
    public function toPrompt(): string
    {
        $references = $this->references
            ->map(fn (SkillReference $reference): string => "## Reference: {$reference->title}\n{$reference->content}")
            ->implode("\n\n");

        return trim("# Skill: {$this->name}\n{$this->instructions}\n\n{$references}");
    }

    /**
     * Bumps `version` for a change to what `toPrompt()` hands the model
     * (the instructions or any reference) so a caller can tell an in-flight
     * session's skill context may have changed.
     */
    public function bumpVersion(): void
    {
        $this->increment('version');
    }

    public function references(): HasMany
    {
        return $this->hasMany(SkillReference::class)->orderBy('sort_order');
    }

    public function scripts(): HasMany
    {
        return $this->hasMany(SkillScript::class);
    }
}
