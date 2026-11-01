<?php

namespace App\Services\Billing;

use App\Enums\Billing\Feature;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;

/**
 * The one place that answers "may this workspace spend past its allowance,
 * and by how much": Gumloop's credit overage: "enable credit overage to
 * keep running past your monthly credits, billed at $0.005 per credit"
 * (docs/gumloop/output/raw/core-concepts/credits.md).
 *
 * Three independent things have to line up before a single overage credit is
 * granted, and each is a deliberate off switch:
 *
 * 1. the workspace's current plan carries the `credit_overage` feature,
 * 2. the workspace has opted in (`credit_overage_enabled`),
 * 3. the current period's overage is still under the effective ceiling.
 *
 * `CreditGate` consults this to decide whether a run may start at a zero
 * balance, and `DeductCreditsAction` to decide how much of a charge overage
 * may absorb. Both read it through the same `remainingFor()` so a run can
 * never be admitted on an allowance the charge would then refuse.
 */
class CreditOverage
{
    /**
     * Whether the workspace's plan sells overage at all: false on the Free
     * plan, matching how `credit_packs` is gated. A workspace that opted in
     * while on a paid plan and then lapsed to Free stops accruing overage
     * without its setting being rewritten, so upgrading restores it.
     */
    public function isAvailableTo(Workspace $workspace): bool
    {
        return $workspace->currentPlan()?->hasFeature(Feature::CreditOverage) ?? false;
    }

    public function isEnabledFor(Workspace $workspace): bool
    {
        return $workspace->credit_overage_enabled && $this->isAvailableTo($workspace);
    }

    /**
     * The ceiling that actually applies this period: the workspace's own cap
     * where it set one, otherwise the estate default, and never above the
     * estate default however the column was written. `null` means uncapped,
     * which only happens when the estate default is itself uncapped:
     * Gumloop's Enterprise case.
     */
    public function effectiveLimitFor(Workspace $workspace): ?int
    {
        $ceiling = $this->maximumLimit();
        $chosen = $workspace->credit_overage_limit;

        if ($chosen === null) {
            return $ceiling;
        }

        if ($ceiling === null) {
            return max(0, $chosen);
        }

        return max(0, min($chosen, $ceiling));
    }

    /**
     * The highest cap a workspace is allowed to set, from
     * `config('billing.overage.default_limit')`. `null` lifts the ceiling
     * estate-wide.
     */
    public function maximumLimit(): ?int
    {
        $limit = config('billing.overage.default_limit');

        return $limit === null ? null : max(0, (int) $limit);
    }

    /**
     * Overage credits this workspace may still spend in `$period`. `0` when
     * overage is unavailable, switched off, or spent; `null` when it is
     * uncapped.
     *
     * Pass the period when one is already loaded (and, in
     * `DeductCreditsAction`'s case, locked): resolving it again there would
     * read around the lock.
     */
    public function remainingFor(Workspace $workspace, ?UsagePeriod $period = null): ?int
    {
        if (! $this->isEnabledFor($workspace)) {
            return 0;
        }

        $limit = $this->effectiveLimitFor($workspace);

        if ($limit === null) {
            return null;
        }

        $period ??= $workspace->currentUsagePeriod();

        return max(0, $limit - $period->overage_credits_used);
    }

    /**
     * Whether `$credits` of overage still fits under the ceiling. Kept here
     * rather than at each call site so "uncapped" doesn't have to be
     * re-handled everywhere `remainingFor()` can return `null`.
     */
    public function canAbsorb(Workspace $workspace, int $credits, ?UsagePeriod $period = null): bool
    {
        $remaining = $this->remainingFor($workspace, $period);

        return $remaining === null || $remaining >= $credits;
    }

    /**
     * What `$credits` of overage costs, in cents, at
     * `config('billing.credit_value_usd')`: Gumloop bills overage at the
     * same $0.005 a credit is worth everywhere else. Rounded up, so a
     * fraction of a cent is never given away.
     */
    public function priceInCents(int $credits): int
    {
        return (int) ceil($credits * (float) config('billing.credit_value_usd') * 100);
    }
}
