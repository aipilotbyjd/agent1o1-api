<?php

namespace App\Contracts\Triggers;

use App\Exceptions\ConnectorException;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;

/**
 * A push source that registers a watch with the provider, which then tells us
 * when something changed. A change makes the trigger poll at once.
 *
 * WatchManager owns the lifecycle (sharing, renewal, removal); a source only
 * says how to make, extend and remove the provider-side registration.
 */
interface WatchedTriggerSource extends PushTriggerSource
{
    /**
     * What the trigger watches, so triggers on the same thing share a watch
     * (for example `inbox`). Null when it can't be watched.
     */
    public function watchKeyFor(Trigger $trigger): ?string;

    /**
     * Register the watch with the provider and fill in what came back:
     * `external_id`, `secret`, `meta`, `expires_at` and `renew_at`.
     *
     * @throws ConnectorException when the account can't be used
     */
    public function createWatch(ProviderWatch $watch, Trigger $trigger): void;

    /**
     * Extend the watch before it expires, or replace it. May change
     * `external_id`, which is what events are routed on.
     */
    public function renewWatch(ProviderWatch $watch): void;

    /**
     * Remove the registration. Best effort: an already-gone watch is fine.
     */
    public function deleteWatch(ProviderWatch $watch): void;
}
