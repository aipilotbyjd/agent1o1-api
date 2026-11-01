<?php

namespace App\Contracts\Triggers;

use App\Models\Triggers\Trigger;

/**
 * Starts whatever a trigger points at, a workflow or an agent.
 *
 * The trigger pipeline (TriggerService, FireTriggerEvent) talks only to this
 * interface, so it never needs to know which kind of target it has.
 * TargetRunStarter is the implementation, bound in AppServiceProvider.
 */
interface RunStarter
{
    /**
     * Whether the target can run right now: a workflow that is published and
     * not deleted, or an agent that is not deleted.
     */
    public function canRun(Trigger $trigger): bool;

    /**
     * Whether the target already has a run in flight.
     *
     * Asked by "Run now" (to answer "busy") and by triggers set to skip
     * events while the last run is still going.
     */
    public function isAlreadyRunning(Trigger $trigger): bool;

    /**
     * How many of the target's runs are executing right now.
     *
     * Runs parked on a person or a callback (an approval, a Wait node) aren't
     * counted: they use no capacity, and one left waiting for days would
     * otherwise hold the trigger's concurrency slot the whole time.
     */
    public function activeRuns(Trigger $trigger): int;

    /**
     * Start the target with an event's payload and return the new run's id.
     *
     * @param  array<string, mixed>  $payload
     */
    public function start(Trigger $trigger, array $payload): string;
}
