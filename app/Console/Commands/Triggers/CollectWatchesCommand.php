<?php

namespace App\Console\Commands\Triggers;

use App\Contracts\Triggers\WatchedTriggerSource;
use App\Models\Triggers\ProviderWatch;
use App\Services\Triggers\Push\PushSources;
use App\Services\Triggers\Push\WatchManager;
use Illuminate\Console\Command;

/**
 * Removes provider watches that no trigger uses any more.
 *
 * A watch is released when its last trigger leaves, and removed after a grace
 * period. This also catches watches whose triggers were deleted underneath
 * them, which never went through the release step.
 */
class CollectWatchesCommand extends Command
{
    protected $signature = 'triggers:collect-watches';

    protected $description = 'Remove provider watches that no trigger uses any more.';

    /**
     * Start the grace period for newly unused watches, then remove the ones
     * whose grace period is over.
     */
    public function handle(PushSources $sources, WatchManager $manager): int
    {
        ProviderWatch::query()
            ->whereNull('release_at')
            ->whereDoesntHave('subscriptions')
            ->where('created_at', '<', now()->subMinutes(5))
            ->update(['release_at' => now()->addMinutes(10)]);

        $removed = 0;

        ProviderWatch::query()
            ->whereNotNull('release_at')
            ->where('release_at', '<=', now())
            ->eachById(function (ProviderWatch $watch) use ($sources, $manager, &$removed): void {
                // Used again during the grace period: keep it.
                if ($watch->subscriptions()->exists()) {
                    $watch->forceFill(['release_at' => null])->save();

                    return;
                }

                $source = $sources->source($watch->provider);

                if ($source instanceof WatchedTriggerSource) {
                    $manager->remove($source, $watch);
                } else {
                    $watch->delete();
                }

                $removed++;
            });

        $this->info("Removed {$removed} unused watch(es).");

        return self::SUCCESS;
    }
}
