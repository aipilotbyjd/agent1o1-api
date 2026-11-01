<?php

namespace App\Contracts;

use Carbon\CarbonInterface;

/**
 * A trigger node whose next look is set by what it just read, not by a fixed
 * interval: "event starting in 15 minutes" has nothing to do until 15 minutes
 * before the next event, so polling every minute until then would be waste.
 *
 * The poll still runs on its usual interval too (a new event can appear at
 * any time, and a push event brings the poll forward): the time returned here
 * can only make the next poll earlier, never later.
 */
interface SchedulesTriggerPolls
{
    /**
     * When the next poll should look, given what this one read, or null to
     * leave it to the usual interval.
     *
     * @param  array<string, mixed>  $output  what execute() returned
     * @param  array<string, mixed>  $config  the node's own config
     */
    public function nextTriggerPollAt(array $output, array $config): ?CarbonInterface;
}
