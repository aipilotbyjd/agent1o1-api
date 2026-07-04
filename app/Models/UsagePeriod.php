<?php

namespace App\Models;

use Database\Factories\UsagePeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'subscription_id', 'period_start', 'period_end', 'credits_limit', 'credits_from_packs', 'credits_rolled_over', 'credits_used', 'executions_total', 'is_current'])]
class UsagePeriod extends Model
{
    /** @use HasFactory<UsagePeriodFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'is_current' => 'boolean',
            'credits_limit' => 'integer',
            'credits_from_packs' => 'integer',
            'credits_rolled_over' => 'integer',
            'credits_used' => 'integer',
            'executions_total' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /** -1 limit is handled by caller — return PHP_INT_MAX for unlimited. */
    public function totalAvailable(): int
    {
        if ($this->credits_limit === -1) {
            return PHP_INT_MAX;
        }

        return $this->credits_limit + $this->credits_from_packs + $this->credits_rolled_over;
    }

    public function creditsRemaining(): int
    {
        if ($this->credits_limit === -1) {
            return PHP_INT_MAX;
        }

        return max(0, $this->totalAvailable() - $this->credits_used);
    }

    public function isUnlimited(): bool
    {
        return $this->credits_limit === -1;
    }
}
