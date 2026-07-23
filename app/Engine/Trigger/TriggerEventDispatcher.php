<?php

namespace App\Engine\Trigger;

use App\Jobs\ProcessTriggerEventJob;
use App\Models\Trigger;
use App\Models\TriggerEvent;
use Illuminate\Support\Facades\DB;

class TriggerEventDispatcher
{
    /**
     * Record an incoming trigger event and queue it for processing.
     *
     * When a $dedupKey is supplied, an event with the same (trigger, dedup_key)
     * that is not in a terminal-failure state suppresses the new one — the
     * method returns null. This guards against duplicate webhook/poll deliveries.
     *
     * @param  array<string, mixed>  $eventData
     */
    public function dispatch(
        Trigger $trigger,
        array $eventData,
        ?string $provider = null,
        ?string $providerEvent = null,
        ?string $dedupKey = null,
    ): ?TriggerEvent {
        $event = DB::transaction(function () use ($trigger, $eventData, $provider, $providerEvent, $dedupKey): ?TriggerEvent {
            if ($dedupKey !== null && $dedupKey !== '') {
                $exists = TriggerEvent::where('trigger_id', $trigger->id)
                    ->where('dedup_key', $dedupKey)
                    ->where('status', '!=', 'failed')
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    return null;
                }
            }

            return TriggerEvent::create([
                'trigger_id' => $trigger->id,
                'target_type' => $trigger->target_type,
                'target_id' => $trigger->target_id,
                'workspace_id' => $trigger->workspace_id,
                'event_data' => $eventData,
                'provider' => $provider,
                'provider_event' => $providerEvent,
                'dedup_key' => $dedupKey,
                'status' => 'pending',
            ]);
        });

        if ($event === null) {
            return null;
        }

        $trigger->increment('total_events');

        ProcessTriggerEventJob::dispatch($event->id)->onQueue('triggers');

        return $event;
    }
}
