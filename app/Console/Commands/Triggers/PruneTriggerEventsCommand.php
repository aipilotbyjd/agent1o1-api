<?php

namespace App\Console\Commands\Triggers;

use App\Enums\Triggers\TriggerType;
use App\Models\Triggers\ProviderEvent;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use Illuminate\Console\Command;

/**
 * Keeps trigger event history from growing forever.
 *
 * Deletes events older than `triggers.events_retention_days`. A polling
 * trigger's old events are compacted instead (payload dropped, delivery id
 * kept), because the delivery id is what stops an item the endpoint still
 * returns from running again.
 */
class PruneTriggerEventsCommand extends Command
{
    protected $signature = 'triggers:prune-events';

    protected $description = 'Delete trigger events older than the retention window, compacting polling events instead.';

    /**
     * Compact old polling events, then delete the rest, in chunks.
     */
    public function handle(): int
    {
        $chunk = max(1, (int) config('triggers.prune_chunk'));
        $cutoff = now()->subDays(max(1, (int) config('triggers.events_retention_days')));

        $pollingTriggerIds = Trigger::query()->where('type', TriggerType::Polling)->select('id');

        $compacted = 0;

        do {
            $ids = TriggerEvent::query()
                ->where('created_at', '<', $cutoff)
                ->whereIn('trigger_id', $pollingTriggerIds)
                ->where(fn ($events) => $events->whereNotNull('payload')->orWhereNotNull('headers')->orWhereNotNull('payload_snippet'))
                ->limit($chunk)
                ->pluck('id');

            $compacted += TriggerEvent::query()
                ->whereIn('id', $ids)
                ->update(['payload' => null, 'headers' => null, 'payload_snippet' => null]);
        } while ($ids->count() === $chunk);

        $deleted = 0;

        do {
            $ids = TriggerEvent::query()
                ->where('created_at', '<', $cutoff)
                ->whereNotIn('trigger_id', $pollingTriggerIds)
                ->limit($chunk)
                ->pluck('id');

            $deleted += TriggerEvent::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() === $chunk);

        $inboxCutoff = now()->subDays(max(1, (int) config('triggers.provider_events_retention_days')));
        $inboxDeleted = 0;

        do {
            $ids = ProviderEvent::query()->where('received_at', '<', $inboxCutoff)->limit($chunk)->pluck('id');
            $inboxDeleted += ProviderEvent::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() === $chunk);

        $this->info("Deleted {$deleted} and compacted {$compacted} trigger event(s), and deleted {$inboxDeleted} provider event(s).");

        return self::SUCCESS;
    }
}
