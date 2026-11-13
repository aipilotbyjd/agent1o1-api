<?php

namespace App\Services\Analytics;

use App\Jobs\Analytics\CapturePostHogEvent;
use Throwable;

/**
 * The one entry point for server-side PostHog events.
 *
 * Events are queued, never sent inline, and a failure here is reported but
 * never thrown: analytics must not break signup, billing or a run. Callers
 * pass ids and counters only. Prompts, agent messages, emails and anything
 * from a credential or secret stay out of `$properties`.
 */
class ProductAnalytics
{
    public function isEnabled(): bool
    {
        return (bool) config('posthog.enabled') && filled(config('posthog.project_token'));
    }

    /**
     * @param  string|null  $userId  Same id the browser identifies with. Null records an
     *                               anonymous system event (no person profile is created).
     * @param  array<string, scalar|null>  $properties
     */
    public function capture(?string $userId, string $event, array $properties = [], ?string $workspaceId = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            if ($userId === null) {
                $properties['$process_person_profile'] = false;
            }

            CapturePostHogEvent::dispatch(
                $userId ?? ($workspaceId !== null ? 'workspace:'.$workspaceId : 'system'),
                $event,
                $properties,
                $workspaceId,
            )->afterCommit();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
