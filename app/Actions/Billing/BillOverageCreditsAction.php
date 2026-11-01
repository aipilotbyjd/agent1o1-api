<?php

namespace App\Actions\Billing;

use App\Enums\Billing\OverageInvoiceAttemptStatus;
use App\Exceptions\BillingAccountNotFoundException;
use App\Models\Billing\OverageInvoiceAttempt;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditOverage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Stripe\Invoice;
use Throwable;

/**
 * Turns accrued overage into a Stripe invoice: the second half of
 * Gumloop's "keep running past your monthly credits, billed at $0.005 per
 * credit". Overage is metered as it is spent (`DeductCreditsAction` writes
 * `usage_periods.overage_credits_used`) and charged after the fact, because
 * invoicing per node run would mean a Stripe call inside the metering path.
 *
 * Claiming the credits and recording a `pending` `OverageInvoiceAttempt`
 * happen in one transaction, *before* the Stripe call; the claim is undone if
 * the call throws. A process that dies in between leaves the pending attempt
 * behind, and the next run reconciles it against Stripe (the invoice carries
 * the attempt id in its metadata): an invoice found means the credits really
 * were billed, none means they go back on the unbilled pile. That keeps the
 * "never double-charge" ordering without being able to lose revenue.
 *
 * A closed period can never accrue more, so what falls under the invoice
 * minimum is carried: the credits of every closed period of a workspace are
 * pooled and invoiced together once they add up to the minimum.
 */
class BillOverageCreditsAction
{
    /** Minutes before a still-pending attempt is presumed to have been interrupted. */
    private const int STALE_ATTEMPT_MINUTES = 30;

    public function __construct(private readonly CreditOverage $overage) {}

    /**
     * Bills everything `$period` has accrued and not yet been invoiced for.
     *
     * @return array{credits: int, amount_cents: int, invoice_id: string|null, periods: int}|null
     *
     * @throws BillingAccountNotFoundException when the workspace never became
     *                                         a Stripe customer, which means
     *                                         its overage cannot be collected.
     */
    public function execute(Workspace $workspace, UsagePeriod $period): ?array
    {
        return $this->executeForPeriods($workspace, collect([$period]));
    }

    /**
     * Bills the unbilled overage of all `$periods` as one invoice. Returns
     * `null` when there is nothing to do (no unbilled overage, or too little
     * of it, in total, to be worth an invoice) so a caller can tell "billed
     * nothing" from "billed zero credits".
     *
     * @param  Collection<int, UsagePeriod>  $periods
     * @return array{credits: int, amount_cents: int, invoice_id: string|null, periods: int}|null
     */
    public function executeForPeriods(Workspace $workspace, Collection $periods): ?array
    {
        $minimumCents = (int) config('billing.overage.minimum_invoice_cents');

        if ($workspace->hasStripeId()) {
            $this->reconcileInterruptedAttempts($workspace);
        }

        $periods = $periods->each(fn (UsagePeriod $period) => $period->refresh());
        $credits = $periods->sum(fn (UsagePeriod $period): int => $period->unbilledOverageCredits());

        if ($credits <= 0 || $this->overage->priceInCents($credits) < $minimumCents) {
            return null;
        }

        if (! $workspace->hasStripeId()) {
            throw new BillingAccountNotFoundException(
                'This workspace accrued overage credits but has no Stripe customer to invoice.',
            );
        }

        $attempt = $this->reserve($workspace, $periods, $minimumCents);

        if ($attempt === null) {
            return null;
        }

        try {
            $invoiceId = $this->invoice(
                $workspace,
                $this->description($attempt->credits, $periods),
                $attempt->amount_cents,
                [
                    'type' => 'credit_overage',
                    'overage_attempt_id' => $attempt->id,
                    'usage_period_ids' => implode(',', array_keys($attempt->allocations)),
                    'overage_credits' => (string) $attempt->credits,
                ],
            );
        } catch (Throwable $e) {
            $this->abandon($attempt);

            throw $e;
        }

        $attempt->forceFill([
            'status' => OverageInvoiceAttemptStatus::Succeeded,
            'stripe_invoice_id' => $invoiceId,
        ])->save();

        return [
            'credits' => $attempt->credits,
            'amount_cents' => $attempt->amount_cents,
            'invoice_id' => $invoiceId,
            'periods' => count($attempt->allocations),
        ];
    }

    /**
     * The one Stripe call this action makes: a single invoice line for the
     * overage, charged to the workspace's default payment method through
     * Cashier. The metadata goes on both the line and the invoice, so an
     * interrupted attempt can be traced either way. Isolated behind its own
     * method so the bookkeeping around it can be exercised without a Stripe
     * account.
     *
     * @param  array<string, string>  $metadata
     */
    protected function invoice(Workspace $workspace, string $description, int $amountCents, array $metadata): ?string
    {
        $attemptId = (string) ($metadata['overage_attempt_id'] ?? '');

        try {
            $created = $this->createAndPayInvoice($workspace, $description, $amountCents, $metadata);
        } catch (Throwable $e) {
            // The call can fail after Stripe has already collected the money
            // (a dropped response), in which case the charge stands and
            // billing the credits again would take it twice.
            $paidInvoiceId = $this->findInvoiceForAttempt($workspace, $attemptId);

            if ($paidInvoiceId !== null) {
                return $paidInvoiceId;
            }

            $this->releaseUnpaidInvoices($workspace, $attemptId);

            throw $e;
        }

        if (($created['status'] ?? null) === 'paid' && isset($created['id'])) {
            return $created['id'];
        }

        // Cashier returns `false` rather than throwing for some refusals, and
        // a declined charge can leave an open invoice behind. Neither is
        // billed, and an open invoice left to Stripe's own retries would
        // collect the same credits a second time once the next run
        // re-invoices them.
        $this->releaseUnpaidInvoices($workspace, $attemptId);

        throw new RuntimeException('The overage invoice was not paid.');
    }

    /**
     * Creates the invoice and asks Stripe to collect it now.
     *
     * @param  array<string, string>  $metadata
     * @return array{id: string|null, status: string|null}|null null when Cashier reports no invoice
     */
    protected function createAndPayInvoice(Workspace $workspace, string $description, int $amountCents, array $metadata): ?array
    {
        $invoice = $workspace->invoiceFor($description, $amountCents, ['metadata' => $metadata], ['metadata' => $metadata]);

        return $invoice ? ['id' => $invoice->id ?? null, 'status' => $invoice->status ?? null] : null;
    }

    /**
     * The id of the *paid* Stripe invoice created for an attempt, if there is
     * one. Listed rather than searched: Stripe's search index lags a fresh
     * invoice by up to a minute, which would read as "none".
     */
    protected function findInvoiceForAttempt(Workspace $workspace, string $attemptId): ?string
    {
        foreach ($this->invoicesForAttempt($workspace, $attemptId) as $invoice) {
            if ($invoice->status === 'paid') {
                return $invoice->id;
            }
        }

        return null;
    }

    /**
     * Voids (or deletes, for a draft) every invoice an attempt left unpaid.
     */
    protected function releaseUnpaidInvoices(Workspace $workspace, string $attemptId): void
    {
        foreach ($this->invoicesForAttempt($workspace, $attemptId) as $invoice) {
            if ($invoice->status === 'open') {
                $workspace->stripe()->invoices->voidInvoice($invoice->id);
            } elseif ($invoice->status === 'draft') {
                $workspace->stripe()->invoices->delete($invoice->id);
            }
        }
    }

    /**
     * @return list<Invoice>
     */
    protected function invoicesForAttempt(Workspace $workspace, string $attemptId): array
    {
        if ($attemptId === '') {
            return [];
        }

        $invoices = $workspace->stripe()->invoices->all(['customer' => $workspace->stripe_id, 'limit' => 100]);

        return array_values(array_filter(
            $invoices->data,
            fn ($invoice): bool => ($invoice->metadata['overage_attempt_id'] ?? null) === $attemptId,
        ));
    }

    /**
     * Removes the pending invoice lines an interrupted attempt left on the
     * customer, so the next invoice does not sweep them up a second time.
     */
    protected function discardOrphanedInvoiceItems(Workspace $workspace, string $attemptId): void
    {
        $items = $workspace->stripe()->invoiceItems->all(['customer' => $workspace->stripe_id, 'pending' => true, 'limit' => 100]);

        foreach ($items->data as $item) {
            if (($item->metadata['overage_attempt_id'] ?? null) === $attemptId) {
                $workspace->stripe()->invoiceItems->delete($item->id);
            }
        }
    }

    /**
     * Settles attempts that were still pending long after they started: the
     * process died between claiming the credits and recording the outcome.
     */
    private function reconcileInterruptedAttempts(Workspace $workspace): void
    {
        OverageInvoiceAttempt::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', OverageInvoiceAttemptStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes(self::STALE_ATTEMPT_MINUTES))
            ->each(function (OverageInvoiceAttempt $attempt) use ($workspace): void {
                $invoiceId = $this->findInvoiceForAttempt($workspace, $attempt->id);

                if ($invoiceId !== null) {
                    $attempt->forceFill([
                        'status' => OverageInvoiceAttemptStatus::Succeeded,
                        'stripe_invoice_id' => $invoiceId,
                    ])->save();

                    return;
                }

                $this->releaseUnpaidInvoices($workspace, $attempt->id);
                $this->discardOrphanedInvoiceItems($workspace, $attempt->id);
                $this->abandon($attempt);
            });
    }

    /**
     * Claims what can be claimed under each period's row lock (so two
     * concurrent runs can't invoice the same overage twice) and records the
     * attempt in the same transaction. Returns `null` when too little was
     * left to claim to be worth an invoice.
     *
     * @param  Collection<int, UsagePeriod>  $periods
     */
    private function reserve(Workspace $workspace, Collection $periods, int $minimumCents): ?OverageInvoiceAttempt
    {
        return DB::transaction(function () use ($workspace, $periods, $minimumCents): ?OverageInvoiceAttempt {
            $allocations = [];

            foreach ($periods->sortBy('id') as $period) {
                $locked = UsagePeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
                $claimable = $locked->unbilledOverageCredits();

                if ($claimable > 0) {
                    $allocations[$period->id] = $claimable;
                }
            }

            $credits = array_sum($allocations);

            // Another run may have claimed most of it between the two reads,
            // leaving too little to be worth an invoice after all.
            if ($credits <= 0 || $this->overage->priceInCents($credits) < $minimumCents) {
                return null;
            }

            foreach ($allocations as $periodId => $claimed) {
                UsagePeriod::whereKey($periodId)->increment('overage_credits_billed', $claimed);
            }

            $periods->each(fn (UsagePeriod $period) => $period->refresh());

            return OverageInvoiceAttempt::create([
                'workspace_id' => $workspace->id,
                'status' => OverageInvoiceAttemptStatus::Pending,
                'credits' => $credits,
                'amount_cents' => $this->overage->priceInCents($credits),
                'allocations' => $allocations,
            ]);
        });
    }

    /**
     * Puts an attempt's credits back on the unbilled pile, so the next run
     * retries them instead of writing them off.
     */
    private function abandon(OverageInvoiceAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            foreach ($attempt->allocations as $periodId => $credits) {
                UsagePeriod::whereKey($periodId)->lockForUpdate()->first()
                    ?->decrement('overage_credits_billed', $credits);
            }

            $attempt->forceFill(['status' => OverageInvoiceAttemptStatus::Failed])->save();
        });
    }

    /**
     * @param  Collection<int, UsagePeriod>  $periods
     */
    private function description(int $credits, Collection $periods): string
    {
        $start = $periods->min('starts_at');
        $end = $periods->max('ends_at');

        $window = $start->format('M j').' – '.$end->clone()->subSecond()->format('M j, Y');

        return number_format($credits).' overage credits ('.$window.')';
    }
}
