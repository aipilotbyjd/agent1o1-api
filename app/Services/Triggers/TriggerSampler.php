<?php

namespace App\Services\Triggers;

use App\Enums\Triggers\TriggerType;
use App\Models\Triggers\Trigger;
use InvalidArgumentException;

/**
 * Shows what a polling trigger sees right now, so a person can map fields
 * from real data before the first event. Reads like a poll does, but records
 * nothing and starts nothing.
 */
class TriggerSampler
{
    public function __construct(private readonly PollReader $reader) {}

    /**
     * The newest items, newest first, as a run would receive them.
     *
     * Each one may cost a call to the app (Gmail fetches every message), so
     * only a few are shown.
     *
     * @return list<array{id: string, payload: array<string, mixed>}>
     *
     * @throws InvalidArgumentException when the trigger starts by hand
     */
    public function sample(Trigger $trigger, int $limit = 5): array
    {
        if ($trigger->type === TriggerType::Webhook) {
            return $this->lastReceived($trigger, $limit);
        }

        if ($trigger->type === TriggerType::Schedule) {
            return [['id' => 'schedule', 'payload' => ['scheduled_at' => ($trigger->next_run_at ?? now())->toIso8601String()]]];
        }

        if ($trigger->type !== TriggerType::Polling) {
            throw new InvalidArgumentException('This trigger starts by hand, so it has no sample.');
        }

        [$items, $idOf, $expand] = $this->reader->read($trigger, forSample: true);

        return array_map(
            fn (array $item): array => ['id' => $idOf($item), 'payload' => $expand($item)],
            array_slice(array_reverse($items), 0, max(1, min($limit, 10))),
        );
    }

    /**
     * A webhook has nothing to read: its sample is what it last received.
     *
     * @return list<array{id: string, payload: array<string, mixed>}>
     */
    private function lastReceived(Trigger $trigger, int $limit): array
    {
        return $trigger->events()
            ->whereNotNull('payload')
            ->latest()
            ->limit(max(1, min($limit, 10)))
            ->get()
            ->map(fn ($event): array => ['id' => $event->delivery_id ?? $event->id, 'payload' => (array) $event->payload])
            ->values()
            ->all();
    }
}
