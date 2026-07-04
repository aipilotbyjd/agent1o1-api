<?php

namespace App\Models;

use App\Enums\Feature;
use App\Enums\Limit;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'price_monthly', 'price_yearly', 'limits', 'features', 'stripe_product_id', 'stripe_prices', 'trial_days', 'is_active', 'sort_order'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
            'stripe_prices' => 'array',
            'is_active' => 'boolean',
            'trial_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasFeature(Feature $feature): bool
    {
        return (bool) ($this->features[$feature->value] ?? false);
    }

    /** Returns the limit value; -1 = unlimited, null treated as -1 (unlimited). */
    public function getLimit(Limit $limit): int
    {
        $value = $this->limits[$limit->value] ?? null;

        return $value === null ? -1 : (int) $value;
    }

    public function isUnlimited(Limit $limit): bool
    {
        return $this->getLimit($limit) === -1;
    }

    public function creditsMonthly(): int
    {
        return $this->getLimit(Limit::CreditsMonthly);
    }
}
