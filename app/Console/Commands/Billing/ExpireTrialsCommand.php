<?php

namespace App\Console\Commands\Billing;

use App\Models\Billing\Subscription;
use Illuminate\Console\Command;

/**
 * Stripe owns the trial → paid transition (it attempts the first charge at
 * `trial_ends_at` and fires `customer.subscription.updated`), but that
 * webhook can be missed. This is the backstop: any subscription whose local
 * `trial_ends_at` has passed while its mirrored `stripe_status` still reads
 * `trialing` gets re-synced straight from Stripe, so a missed webhook can't
 * leave a workspace looking like it's still on trial indefinitely.
 */
class ExpireTrialsCommand extends Command
{
    protected $signature = 'billing:expire-trials';

    protected $description = 'Re-syncs subscriptions whose trial has ended but are still locally marked as trialing.';

    public function handle(): int
    {
        $stale = Subscription::query()
            ->where('stripe_status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->get();

        foreach ($stale as $subscription) {
            $subscription->syncStripeStatus();
        }

        $this->info("Re-synced {$stale->count()} stale trial subscription(s).");

        return self::SUCCESS;
    }
}
