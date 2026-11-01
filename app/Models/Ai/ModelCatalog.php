<?php

namespace App\Models\Ai;

use App\Enums\Ai\ModelSpeed;
use App\Models\Agents\Agent;
use App\Models\Billing\Plan;
use Database\Factories\Ai\ModelCatalogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The public, brand-facing model identity shown to end users (agent/workflow
 * model pickers): deliberately decoupled from how a prompt actually gets
 * executed. What runs a given entry (a direct provider, or one/more
 * aggregators like Fireworks/Together/OpenRouter, in priority order) lives
 * in `ModelRoute` and is never exposed through user-facing API responses.
 * See `Services\Ai\ModelCatalogResolver`.
 */
#[Fillable(['slug', 'display_name', 'brand', 'description', 'strengths', 'speed', 'cost_tier', 'capabilities', 'is_active', 'is_internal', 'sort_order', 'retires_at', 'replacement_slug', 'min_plan_id'])]
class ModelCatalog extends Model
{
    /** @use HasFactory<ModelCatalogFactory> */
    use HasFactory, HasUuids;

    protected $table = 'model_catalog';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'strengths' => 'array',
            'speed' => ModelSpeed::class,
            'cost_tier' => 'integer',
            'is_active' => 'boolean',
            'is_internal' => 'boolean',
            'retires_at' => 'datetime',
        ];
    }

    /**
     * Candidate execution backends for this entry, in the order
     * `ModelCatalogResolver` should try them.
     */
    public function routes(): HasMany
    {
        return $this->hasMany(ModelRoute::class)->orderBy('priority');
    }

    /**
     * Past its retirement: switched off, or its `retires_at` has passed.
     */
    public function isRetired(): bool
    {
        return ! $this->is_active || ($this->retires_at !== null && $this->retires_at->isPast());
    }

    /**
     * Retired with a replacement named: requests for it are served by the
     * replacement (see `ModelCatalogResolver`). A retired entry with no
     * replacement is simply unavailable, as before.
     */
    public function hasHandedOver(): bool
    {
        return $this->isRetired() && filled($this->replacement_slug);
    }

    /**
     * The cheapest plan allowed to use this model, if it is limited by plan.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function minPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'min_plan_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}
