<?php

namespace App\Actions\Billing;

use App\Enums\Billing\CreditGrantSource;
use App\Enums\Billing\CreditPackStatus;
use App\Models\Billing\CreditPack;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditWallet;
use Illuminate\Support\Facades\DB;

/**
 * Credits a workspace for a paid-for pack with a never-expiring `topup`
 * grant in its `CreditWallet`, spent after every other kind of credit.
 * The grant references the pack, so a refund or dispute of the payment can
 * find and revoke it. Packs deliberately don't touch the current usage
 * period: raising `usage_periods.credits_limit` (as this used to) meant
 * purchased credits were thrown away at month rollover, were wiped outright
 * the next time `OpenUsagePeriodForSubscriptionAction` reset the limit from
 * the plan, and were silently dropped altogether on an unlimited period:
 * a customer paying and receiving nothing.
 *
 * Packs also don't write to `credit_transactions`; that ledger is
 * consumption-only (see `DeductCreditsAction`), and each charge records how
 * much of itself came out of this pool.
 *
 * Idempotent: safe to call more than once for the same pack (e.g. a
 * redelivered webhook), since `Active` packs are a no-op.
 */
class ActivateCreditPackAction
{
    public function __construct(private readonly CreditWallet $wallet) {}

    public function execute(CreditPack $pack): void
    {
        if ($pack->status === CreditPackStatus::Active) {
            return;
        }

        DB::transaction(function () use ($pack): void {
            $workspace = Workspace::whereKey($pack->workspace_id)->lockForUpdate()->firstOrFail();

            $this->wallet->grant($workspace, CreditGrantSource::Topup, $pack->credits_amount, [
                'idempotency_key' => 'pack:'.$pack->id,
                'reference' => $pack,
                'stripe_reference' => $pack->stripe_payment_intent_id,
                'granted_by' => $pack->purchased_by,
                'reason' => 'Credit pack: '.$pack->pack_key,
            ]);

            $pack->update([
                'status' => CreditPackStatus::Active,
                'purchased_at' => now(),
            ]);
        });
    }
}
