<?php

namespace App\Services\Billing;

use App\Models\Workspaces\Workspace;

/**
 * Credits or debits a workspace's Stripe customer balance: money off (or
 * onto) its next invoice, never a payout. Wrapped so the referral program's
 * `StripeBalanceRewardHandler` can be exercised without calling Stripe.
 */
class StripeCustomerBalance
{
    /**
     * With an `$operationKey`, the call is safe to repeat: the key is stored
     * on the balance transaction, and an earlier transaction carrying it is
     * returned instead of applying the amount a second time: covering a
     * crash after Stripe accepted the call but before the caller recorded it.
     *
     * @return string The Stripe customer balance transaction id.
     */
    public function credit(Workspace $workspace, int $amountCents, string $description, ?string $operationKey = null): string
    {
        return $this->apply($workspace, -$amountCents, $description, $operationKey);
    }

    /**
     * @return string The Stripe customer balance transaction id.
     */
    public function debit(Workspace $workspace, int $amountCents, string $description, ?string $operationKey = null): string
    {
        return $this->apply($workspace, $amountCents, $description, $operationKey);
    }

    private function apply(Workspace $workspace, int $signedAmountCents, string $description, ?string $operationKey): string
    {
        $workspace->createOrGetStripeCustomer();

        if ($operationKey === null) {
            return $workspace->applyBalance($signedAmountCents, $description)->id;
        }

        $existing = $workspace->balanceTransactions(100)->first(
            fn ($transaction): bool => ($transaction->asStripeCustomerBalanceTransaction()->metadata['operation_key'] ?? null) === $operationKey,
        );

        return $existing?->id
            ?? $workspace->applyBalance($signedAmountCents, $description, ['metadata' => ['operation_key' => $operationKey]])->id;
    }
}
