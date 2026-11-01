<?php

namespace App\Console\Commands\Triggers;

use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\RenewProviderWatch;
use App\Models\Triggers\ProviderWatch;
use Illuminate\Console\Command;

/**
 * Queues a renewal for every provider watch that is due, found with an index
 * on `renew_at` rather than by looking at every watch.
 */
class RenewWatchesCommand extends Command
{
    protected $signature = 'triggers:renew-watches';

    protected $description = 'Queue a renewal for every provider watch that is due to be extended.';

    /**
     * Queue the due renewals.
     */
    public function handle(): int
    {
        $queued = 0;

        ProviderWatch::query()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Degraded->value])
            ->whereNotNull('renew_at')
            ->where('renew_at', '<=', now())
            ->whereNull('release_at')
            ->eachById(function (ProviderWatch $watch) use (&$queued): void {
                RenewProviderWatch::dispatch($watch->id);
                $queued++;
            });

        $this->info("Queued {$queued} watch renewal(s).");

        return self::SUCCESS;
    }
}
