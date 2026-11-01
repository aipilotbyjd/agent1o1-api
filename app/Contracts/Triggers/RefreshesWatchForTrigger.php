<?php

namespace App\Contracts\Triggers;

use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;

/**
 * A watched source whose one shared watch covers what its triggers ask for (the
 * labels of a mailbox), so it can go stale when a trigger that needs more joins.
 */
interface RefreshesWatchForTrigger
{
    /**
     * Whether the existing watch no longer covers the trigger, and so must be
     * registered again (renewWatch) before the trigger relies on it.
     */
    public function watchIsStale(ProviderWatch $watch, Trigger $trigger): bool;
}
