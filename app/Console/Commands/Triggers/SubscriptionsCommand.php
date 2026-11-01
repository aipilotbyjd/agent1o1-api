<?php

namespace App\Console\Commands\Triggers;

use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\RenewProviderWatch;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\TriggerSubscription;
use Illuminate\Console\Command;

/**
 * What the push subscriptions and provider watches look like right now, and a
 * way to nudge the ones that are stuck.
 */
class SubscriptionsCommand extends Command
{
    protected $signature = 'triggers:subscriptions
        {--renew : Renew every watch that expires within 24 hours}
        {--resync : Re-check every subscription that is not active}';

    protected $description = 'Show push subscriptions and provider watches by provider and status, and optionally renew or re-check them.';

    /**
     * Print the summary, then do what was asked.
     */
    public function handle(): int
    {
        $this->line('Subscriptions');
        $this->table(['Provider', 'Status', 'Count'], TriggerSubscription::query()
            ->selectRaw('provider, status, count(*) as total')
            ->groupBy('provider', 'status')
            ->orderBy('provider')
            ->get()
            ->map(fn ($row): array => [$row->provider, $row->status->value, $row->total])
            ->all());

        $this->line('Provider watches');
        $this->table(['Provider', 'Status', 'Count', 'Expiring in 24h'], ProviderWatch::query()
            ->selectRaw('provider, status, count(*) as total')
            ->groupBy('provider', 'status')
            ->orderBy('provider')
            ->get()
            ->map(fn ($row): array => [
                $row->provider,
                $row->status->value,
                $row->total,
                ProviderWatch::query()->where('provider', $row->provider)->where('status', $row->status->value)->whereBetween('expires_at', [now(), now()->addDay()])->count(),
            ])
            ->all());

        if ($this->option('renew')) {
            $count = 0;

            ProviderWatch::query()->where('expires_at', '<=', now()->addDay())->whereNull('release_at')->eachById(function (ProviderWatch $watch) use (&$count): void {
                RenewProviderWatch::dispatch($watch->id);
                $count++;
            });

            $this->info("Queued {$count} renewal(s).");
        }

        if ($this->option('resync')) {
            $count = 0;

            TriggerSubscription::query()->where('status', '!=', SubscriptionStatus::Active->value)->eachById(function (TriggerSubscription $subscription) use (&$count): void {
                SyncTriggerSubscription::dispatch($subscription->trigger_id);
                $count++;
            });

            $this->info("Queued {$count} re-check(s).");
        }

        return self::SUCCESS;
    }
}
