<?php

namespace App\Services\Billing;

use App\Enums\Billing\CreditGrantSource;
use App\Models\Billing\CreditGrant;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * The only code that changes a workspace's credit balance. Every credit is a
 * `CreditGrant` with a source, an expiry and a priority; charges walk the
 * spendable grants in order and record which grants paid in
 * `credit_grant_allocations`.
 *
 * Two legacy fields stay in step so existing readers keep working:
 *
 * - `usage_periods.credits_limit` is the size of the period's allowance (the
 *   plan, free or trial grant). Policies set it; `ensureAllowance()` keeps the
 *   period's allowance grant matching it. `null` still means unlimited.
 * - `workspaces.topup_credits` mirrors the credits left in carry-over grants
 *   (top-up, promo, referral, admin, migration). A legacy write to it is
 *   absorbed by `reconcileCarryOver()` on the next charge.
 *
 * `workspaces.credit_debt` is work that already ran but could not be paid
 * for; the next grant pays it first.
 */
class CreditWallet
{
    /**
     * Adds credits to a workspace. Idempotent per `idempotency_key`: a second
     * call with the same key returns the first grant untouched, so a
     * redelivered webhook never grants twice.
     *
     * @param  array{
     *     idempotency_key?: string,
     *     starts_at?: CarbonInterface|null,
     *     expires_at?: CarbonInterface|null,
     *     usage_period_id?: string|null,
     *     reference?: Model|null,
     *     stripe_reference?: string|null,
     *     granted_by?: string|null,
     *     reason?: string|null,
     *     priority?: int|null,
     *     remaining?: int|null,
     *     settle_debt?: bool,
     * }  $options
     */
    public function grant(Workspace $workspace, CreditGrantSource $source, int $amount, array $options = []): CreditGrant
    {
        if ($amount < 0) {
            throw new LogicException('A grant cannot be negative.');
        }

        $key = $options['idempotency_key'] ?? $source->value.':'.Str::uuid();

        $existing = CreditGrant::query()->where('idempotency_key', $key)->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($workspace, $source, $amount, $options, $key): CreditGrant {
                $locked = Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();

                if (! $source->isAllowance()) {
                    $this->reconcileCarryOver($locked);
                }

                $remaining = min($amount, max(0, $options['remaining'] ?? $amount));
                $startsAt = $options['starts_at'] ?? now();
                $reference = $options['reference'] ?? null;

                $settled = 0;

                if (($options['settle_debt'] ?? true) && $locked->credit_debt > 0 && $startsAt->lte(now())) {
                    $settled = min($locked->credit_debt, $remaining);
                    $remaining -= $settled;
                }

                $grant = CreditGrant::query()->create([
                    'workspace_id' => $locked->id,
                    'usage_period_id' => $options['usage_period_id'] ?? null,
                    'source' => $source,
                    'amount' => $amount,
                    'remaining' => $remaining,
                    'priority' => $options['priority'] ?? $source->defaultPriority(),
                    'starts_at' => $startsAt,
                    'expires_at' => $options['expires_at'] ?? null,
                    'reference_type' => $reference?->getMorphClass(),
                    'reference_id' => $reference?->getKey(),
                    'stripe_reference' => $options['stripe_reference'] ?? null,
                    'idempotency_key' => $key,
                    'granted_by' => $options['granted_by'] ?? null,
                    'reason' => $settled > 0
                        ? trim(($options['reason'] ?? '').' (paid '.$settled.' credits of debt)')
                        : ($options['reason'] ?? null),
                ]);

                if ($settled > 0) {
                    $locked->decrement('credit_debt', $settled);
                }

                if (! $source->isAllowance()) {
                    $this->syncCarryOverMirror($locked);
                }

                return $grant;
            });
        } catch (UniqueConstraintViolationException) {
            return CreditGrant::query()->where('idempotency_key', $key)->firstOrFail();
        }
    }

    /**
     * Takes up to `$credits` from the workspace's spendable grants, in spend
     * order, and returns what each grant paid plus whatever was left unpaid.
     * Must run inside the caller's transaction, after the workspace row is
     * locked, so two charges cannot spend the same credits.
     *
     * @return array{allocations: list<array{grant: CreditGrant, credits: int}>, allowance: int, carry_over: int, shortfall: int}
     */
    public function spend(Workspace $lockedWorkspace, int $credits): array
    {
        $this->assertInTransaction();

        $allocations = [];
        $allowance = 0;
        $carryOver = 0;
        $left = $credits;

        if ($left > 0) {
            /** @var Collection<int, CreditGrant> $grants */
            $grants = CreditGrant::query()
                ->where('workspace_id', $lockedWorkspace->id)
                ->spendable()
                ->lockForUpdate()
                ->get();

            foreach ($grants as $grant) {
                if ($left === 0) {
                    break;
                }

                $take = min($left, $grant->remaining);
                $grant->decrement('remaining', $take);
                $left -= $take;

                $allocations[] = ['grant' => $grant, 'credits' => $take];

                if ($grant->source->isAllowance()) {
                    $allowance += $take;
                } else {
                    $carryOver += $take;
                }
            }
        }

        if ($carryOver > 0) {
            $this->syncCarryOverMirror($lockedWorkspace);
        }

        return ['allocations' => $allocations, 'allowance' => $allowance, 'carry_over' => $carryOver, 'shortfall' => $left];
    }

    /**
     * @param  list<array{grant: CreditGrant, credits: int}>  $allocations
     */
    public function recordAllocations(CreditTransaction $transaction, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            $transaction->allocations()->create([
                'credit_grant_id' => $allocation['grant']->id,
                'credits' => $allocation['credits'],
            ]);
        }
    }

    /**
     * Records work that already ran but that no grant could pay for.
     */
    public function addDebt(Workspace $lockedWorkspace, int $credits): void
    {
        $this->assertInTransaction();

        if ($credits > 0) {
            $lockedWorkspace->increment('credit_debt', $credits);
        }
    }

    /**
     * Credits spendable right now, net of debt. Can be negative.
     */
    public function balance(Workspace $workspace): int
    {
        return $this->allowanceRemaining($workspace) + $this->carryOverRemaining($workspace) - (int) $workspace->credit_debt;
    }

    /**
     * What is left of the current period's allowance (plan, free or trial).
     */
    public function allowanceRemaining(Workspace $workspace): int
    {
        $period = $workspace->currentUsagePeriod();
        $grant = $this->ensureAllowance($period);

        return $grant !== null && $grant->isSpendable() ? $grant->remaining : 0;
    }

    /**
     * Credits left in spendable carry-over grants, plus any legacy write to
     * the mirror column not yet absorbed into a grant.
     */
    public function carryOverRemaining(Workspace $workspace): int
    {
        $spendable = $this->carryOverInGrants($workspace);

        $unexpired = (int) CreditGrant::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('source', CreditGrantSource::carryOver())
            ->whereNull('revoked_at')
            ->whereNull('expired_at')
            ->sum('remaining');

        return $spendable + max(0, (int) $workspace->topup_credits - $unexpired);
    }

    /**
     * The spendable grants, in the order they will be spent, for the
     * billing page's breakdown.
     *
     * @return Collection<int, CreditGrant>
     */
    public function spendableGrants(Workspace $workspace): Collection
    {
        $this->ensureAllowance($workspace->currentUsagePeriod());

        return CreditGrant::query()->where('workspace_id', $workspace->id)->spendable()->get();
    }

    /**
     * What a period's plan allowance had left when it ended, however its
     * grant was retired. A grant's `remaining` is zeroed when a billing cycle
     * closes it and again when `credits:expire` lapses it, so it cannot say
     * what went unused; the grant's size less what the period spent can.
     */
    public function unusedPlanAllowance(UsagePeriod $period): int
    {
        $grant = CreditGrant::query()
            ->where('usage_period_id', $period->id)
            ->where('source', CreditGrantSource::Plan)
            ->latest('created_at')
            ->latest('id')
            ->first();

        return $grant === null ? 0 : max(0, $grant->amount - $this->allowanceSpentBeforeWallet($period));
    }

    /**
     * Keeps the period's allowance grant matching `credits_limit`, creating
     * it the first time the period is used. A changed limit (a plan change
     * mid-period) replaces the grant: what was already spent from the old
     * one counts against the new size, as it always has.
     */
    public function ensureAllowance(UsagePeriod $period, ?CreditGrantSource $source = null): ?CreditGrant
    {
        $current = CreditGrant::query()
            ->where('usage_period_id', $period->id)
            ->whereIn('source', CreditGrantSource::allowance())
            ->whereNull('revoked_at')
            ->latest('created_at')
            ->latest('id')
            ->first();

        if ($period->credits_limit === null) {
            if ($current !== null) {
                $this->revoke($current, 'Period became unlimited');
            }

            return null;
        }

        $limit = (int) $period->credits_limit;
        $source ??= $current->source ?? CreditGrantSource::Plan;

        if ($current !== null && $current->amount === $limit && $current->source === $source) {
            $this->catchUpWithPeriodUsage($period, $current);

            return $current;
        }

        // A period that has ended keeps whatever grant it had: an allowance is
        // never created or resized after the fact.
        if ($period->ends_at !== null && $period->ends_at->lte(now())) {
            return $current;
        }

        $alreadySpent = $current !== null ? $current->spent() : $this->allowanceSpentBeforeWallet($period);
        $version = CreditGrant::query()->where('usage_period_id', $period->id)->whereIn('source', CreditGrantSource::allowance())->count() + 1;

        if ($current !== null) {
            $this->revoke($current, 'Allowance resized to '.$limit);
        }

        return $this->grant($period->workspace, $source, $limit, [
            'idempotency_key' => "allowance:{$period->id}:v{$version}",
            'usage_period_id' => $period->id,
            'starts_at' => $period->starts_at,
            'expires_at' => $period->ends_at,
            'remaining' => max(0, $limit - $alreadySpent),
            'settle_debt' => $current === null,
            'reason' => $current === null ? 'Allowance for the period' : 'Allowance resized',
        ]);
    }

    /**
     * Absorbs legacy writes to `workspaces.topup_credits`: an increase becomes
     * a `migration` grant, a decrease is taken from carry-over grants. Runs
     * inside the caller's transaction with the workspace locked.
     */
    public function reconcileCarryOver(Workspace $lockedWorkspace): void
    {
        $this->assertInTransaction();

        $mirror = (int) $lockedWorkspace->topup_credits - $this->expireDueCarryOver($lockedWorkspace);
        $inGrants = $this->carryOverInGrants($lockedWorkspace);

        if ($mirror === $inGrants) {
            return;
        }

        if ($mirror > $inGrants) {
            CreditGrant::query()->create([
                'workspace_id' => $lockedWorkspace->id,
                'source' => CreditGrantSource::Migration,
                'amount' => $mirror - $inGrants,
                'remaining' => $mirror - $inGrants,
                'priority' => CreditGrantSource::Migration->defaultPriority(),
                'starts_at' => now(),
                'idempotency_key' => 'migration:'.Str::uuid(),
                'reason' => 'Carried over from the previous balance',
            ]);

            return;
        }

        $this->takeFromCarryOver($lockedWorkspace, $inGrants - $mirror, 'Balance corrected');
    }

    /**
     * Revokes what is left of a grant. With `$spentBecomesDebt`, the part
     * already spent is added to the workspace's debt: used for refunds and
     * disputes, where the customer has the money back but used the credits.
     * Returns how many unspent credits were revoked.
     */
    public function revoke(CreditGrant $grant, string $reason, bool $spentBecomesDebt = false): int
    {
        return DB::transaction(function () use ($grant, $reason, $spentBecomesDebt): int {
            $workspace = Workspace::whereKey($grant->workspace_id)->lockForUpdate()->firstOrFail();
            $locked = CreditGrant::whereKey($grant->id)->lockForUpdate()->firstOrFail();

            if ($locked->revoked_at !== null) {
                return 0;
            }

            if (! $locked->source->isAllowance()) {
                $this->reconcileCarryOver($workspace);
                $locked->refresh();
            }

            $unspent = $locked->expired_at === null ? $locked->remaining : 0;

            $locked->update([
                'remaining' => 0,
                'revoked_at' => now(),
                'revoke_reason' => Str::limit($reason, 250),
            ]);

            if ($spentBecomesDebt) {
                $spent = (int) $locked->allocations()->sum('credits');

                if ($spent > 0) {
                    $workspace->increment('credit_debt', $spent);
                }
            }

            if (! $locked->source->isAllowance()) {
                $this->syncCarryOverMirror($workspace);
            }

            $grant->setRawAttributes($locked->getAttributes(), true);

            return $unspent;
        });
    }

    /**
     * Removes up to `$credits` from carry-over grants (an admin revoke, a
     * clawed-back referral reward), in spend order. Returns what was removed.
     *
     * @param  list<CreditGrantSource>|null  $sources  Limit to these sources.
     */
    public function revokeCredits(Workspace $workspace, int $credits, string $reason, ?array $sources = null): int
    {
        return DB::transaction(function () use ($workspace, $credits, $reason, $sources): int {
            $locked = Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $this->reconcileCarryOver($locked);

            $removed = $this->takeFromCarryOver($locked, $credits, $reason, $sources);
            $this->syncCarryOverMirror($locked);

            return $removed;
        });
    }

    /**
     * Marks every grant past its expiry as expired. Returns how many credits
     * lapsed.
     */
    public function expireDue(): int
    {
        $lapsed = 0;

        CreditGrant::query()
            ->whereNull('expired_at')
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(200, function (Collection $grants) use (&$lapsed): void {
                foreach ($grants as $grant) {
                    $lapsed += DB::transaction(function () use ($grant): int {
                        $workspace = Workspace::whereKey($grant->workspace_id)->lockForUpdate()->first();
                        $locked = CreditGrant::whereKey($grant->id)->lockForUpdate()->first();

                        if ($locked === null || $locked->expired_at !== null || $locked->revoked_at !== null) {
                            return 0;
                        }

                        if ($workspace !== null && ! $locked->source->isAllowance()) {
                            $before = (int) $workspace->topup_credits;
                            $this->reconcileCarryOver($workspace);
                            $this->syncCarryOverMirror($workspace);

                            return max(0, $before - (int) $workspace->topup_credits);
                        }

                        $left = $locked->remaining;
                        $locked->update(['remaining' => 0, 'expired_at' => now()]);

                        return $left;
                    });
                }
            });

        return $lapsed;
    }

    /**
     * Sets `workspaces.topup_credits` to what carry-over grants hold.
     */
    public function syncCarryOverMirror(Workspace $workspace): void
    {
        $inGrants = $this->carryOverInGrants($workspace);

        if ((int) $workspace->topup_credits !== $inGrants) {
            Workspace::whereKey($workspace->id)->update(['topup_credits' => $inGrants]);
        }

        $workspace->topup_credits = $inGrants;
        $workspace->syncOriginalAttribute('topup_credits');
    }

    /**
     * `credits_used` stays the period's usage counter. The allowance grant
     * never shows more left than the limit minus the usage the allowance paid
     * for, so a usage write that did not go through `DeductCreditsAction`
     * (an import, a manual correction) is still respected.
     */
    private function catchUpWithPeriodUsage(UsagePeriod $period, CreditGrant $grant): void
    {
        if ($grant->revoked_at !== null || $grant->expired_at !== null) {
            return;
        }

        $expectedRemaining = max(0, $grant->amount - $this->allowanceSpentBeforeWallet($period));

        if ($grant->remaining > $expectedRemaining) {
            CreditGrant::whereKey($grant->id)->where('remaining', '>', $expectedRemaining)->update(['remaining' => $expectedRemaining]);
            $grant->remaining = $expectedRemaining;
            $grant->syncOriginalAttribute('remaining');
        }
    }

    /**
     * How much of a period's usage its allowance paid for: the whole usage
     * minus what carry-over grants, overage and debt covered.
     */
    private function allowanceSpentBeforeWallet(UsagePeriod $period): int
    {
        $paidElsewhere = (int) CreditTransaction::query()
            ->where('usage_period_id', $period->id)
            ->selectRaw('coalesce(sum(topup_credits + overage_credits + debt_credits), 0) as paid')
            ->value('paid');

        return max(0, (int) $period->credits_used - $paidElsewhere);
    }

    /**
     * Marks this workspace's carry-over grants that are past their expiry
     * as expired and returns how many credits lapsed, so the mirror can drop
     * them before it is compared with the grants.
     */
    private function expireDueCarryOver(Workspace $lockedWorkspace): int
    {
        $due = CreditGrant::query()
            ->where('workspace_id', $lockedWorkspace->id)
            ->whereIn('source', CreditGrantSource::carryOver())
            ->whereNull('revoked_at')
            ->whereNull('expired_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->lockForUpdate()
            ->get();

        $lapsed = 0;

        foreach ($due as $grant) {
            $lapsed += $grant->remaining;
            $grant->update(['remaining' => 0, 'expired_at' => now()]);
        }

        return $lapsed;
    }

    private function carryOverInGrants(Workspace $workspace): int
    {
        return (int) CreditGrant::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('source', CreditGrantSource::carryOver())
            ->spendable()
            ->reorder()
            ->sum('remaining');
    }

    /**
     * @param  list<CreditGrantSource>|null  $sources
     */
    private function takeFromCarryOver(Workspace $lockedWorkspace, int $credits, string $reason, ?array $sources = null): int
    {
        $left = $credits;

        $grants = CreditGrant::query()
            ->where('workspace_id', $lockedWorkspace->id)
            ->whereIn('source', $sources ?? CreditGrantSource::carryOver())
            ->spendable()
            ->lockForUpdate()
            ->get();

        foreach ($grants as $grant) {
            if ($left === 0) {
                break;
            }

            $take = min($left, $grant->remaining);
            $grant->update([
                'remaining' => $grant->remaining - $take,
                'revoke_reason' => Str::limit($reason, 250),
            ]);
            $left -= $take;
        }

        return $credits - $left;
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Wallet changes must run inside a database transaction with the workspace locked.');
        }
    }
}
