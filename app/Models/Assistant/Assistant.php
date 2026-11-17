<?php

namespace App\Models\Assistant;

use App\Models\Ai\ModelCatalog;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Assistant\AssistantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A user's personal assistant: one per (workspace, user), created on first
 * open by `AssistantProvisioner` and visible to its owner only. Deliberately
 * separate from `Agent`: see docs/ASSISTANT_PLAN.md. Its public name is not
 * stored here: it comes from `BrandRepository`.
 */
#[Fillable(['workspace_id', 'user_id', 'model_catalog_id', 'instructions', 'settings'])]
class Assistant extends Model
{
    /** @use HasFactory<AssistantFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ModelCatalog, $this>
     */
    public function modelCatalog(): BelongsTo
    {
        return $this->belongsTo(ModelCatalog::class);
    }

    /**
     * @return HasMany<AssistantSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(AssistantSession::class);
    }

    /**
     * @return HasMany<AssistantMemory, $this>
     */
    public function memories(): HasMany
    {
        return $this->hasMany(AssistantMemory::class);
    }

    /**
     * @return HasMany<AssistantFollowUp, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(AssistantFollowUp::class);
    }

    /**
     * @return HasMany<AssistantStyleProfile, $this>
     */
    public function styleProfiles(): HasMany
    {
        return $this->hasMany(AssistantStyleProfile::class);
    }

    /**
     * @return HasMany<AssistantFeedback, $this>
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(AssistantFeedback::class);
    }

    /**
     * @return HasMany<AssistantBriefingConfig, $this>
     */
    public function briefingConfigs(): HasMany
    {
        return $this->hasMany(AssistantBriefingConfig::class);
    }

    /**
     * @return HasMany<AssistantMeeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(AssistantMeeting::class);
    }

    /**
     * @return HasOne<AssistantInboxConfig, $this>
     */
    public function inbox(): HasOne
    {
        return $this->hasOne(AssistantInboxConfig::class);
    }

    /**
     * @return HasMany<AssistantTrigger, $this>
     */
    public function triggers(): HasMany
    {
        return $this->hasMany(AssistantTrigger::class);
    }

    /**
     * @return HasMany<AssistantSituation, $this>
     */
    public function situations(): HasMany
    {
        return $this->hasMany(AssistantSituation::class);
    }

    /**
     * @return HasMany<AssistantToolRule, $this>
     */
    public function toolRules(): HasMany
    {
        return $this->hasMany(AssistantToolRule::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
