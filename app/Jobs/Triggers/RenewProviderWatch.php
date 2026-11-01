<?php

namespace App\Jobs\Triggers;

use App\Contracts\Triggers\WatchedTriggerSource;
use App\Models\Triggers\ProviderWatch;
use App\Services\Triggers\Push\PushSources;
use App\Services\Triggers\Push\WatchManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Extends one provider watch before it expires.
 *
 * Renewal failures are recorded on the watch by WatchManager and retried by the
 * next `triggers:renew-watches` run, so this job itself never fails loudly.
 */
class RenewProviderWatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $watchId)
    {
        $this->onQueue((string) config('triggers.fire_queue'));
    }

    /**
     * One renewal per watch at a time.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->watchId))->releaseAfter(30)->expireAfter(180)];
    }

    /**
     * Renew the watch.
     */
    public function handle(PushSources $sources, WatchManager $manager): void
    {
        $watch = ProviderWatch::query()->find($this->watchId);

        if ($watch === null) {
            return;
        }

        $source = $sources->source($watch->provider);

        if ($source instanceof WatchedTriggerSource) {
            $manager->renew($source, $watch);
        }
    }
}
