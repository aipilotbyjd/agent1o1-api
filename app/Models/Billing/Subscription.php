<?php

namespace App\Models\Billing;

use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    use HasUuids;

    /**
     * Only the columns this subclass adds: Laravel merges these over
     * Cashier's own `$casts` property, so redeclaring its dates here would
     * just risk drifting from them.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dunning_started_at' => 'datetime',
            'dunning_attempts' => 'integer',
            'trial_ending_notified_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
        ];
    }

    /**
     * Whether Stripe is currently failing to collect on this subscription.
     *
     * Deliberately not an entitlement check: this app grants no grace period,
     * so `Workspace::activeSubscription()` has already withdrawn the plan by
     * the time this returns true. It exists so the billing screen can say why.
     */
    public function inDunning(): bool
    {
        return $this->dunning_started_at !== null;
    }

    /**
     * Records a failed collection attempt. `dunning_started_at` is set once
     * and then left alone, so it marks the beginning of the failing cycle
     * rather than the latest retry.
     */
    public function markDunning(?string $invoiceId, int $attempts): void
    {
        $this->forceFill([
            'dunning_started_at' => $this->dunning_started_at ?? now(),
            'dunning_invoice_id' => $invoiceId,
            'dunning_attempts' => max($attempts, $this->dunning_attempts + 1),
        ])->save();
    }

    /**
     * Collection succeeded: the cycle is over. Returns whether there was
     * actually a cycle to clear, so callers only announce a recovery that the
     * customer was told about in the first place.
     */
    public function clearDunning(): bool
    {
        if (! $this->inDunning()) {
            return false;
        }

        $this->forceFill([
            'dunning_started_at' => null,
            'dunning_invoice_id' => null,
            'dunning_attempts' => 0,
        ])->save();

        return true;
    }

    /**
     * Records that the trial-ending reminder was sent, so
     * `billing:notify-trial-ending` never sends it twice for the same trial.
     */
    public function markTrialEndingNotified(): void
    {
        $this->forceFill(['trial_ending_notified_at' => now()])->save();
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Alias of Cashier's owner() relation: this app's billable model is Workspace.
     *
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->owner();
    }
}
