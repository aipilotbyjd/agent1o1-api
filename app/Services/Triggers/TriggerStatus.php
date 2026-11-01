<?php

namespace App\Services\Triggers;

use App\Contracts\Triggers\NeedsPushDelivery;
use App\Enums\Triggers\SubscriptionStatus;
use App\Enums\Triggers\TriggerDisabledReason;
use App\Enums\Triggers\TriggerType;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\Trigger;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Carbon;

/**
 * What a person is told about a trigger: how it gets its events and whether it
 * is working, in a few states that each come with one thing to do.
 *
 * Computed from the trigger and its subscription, so the editor, the settings
 * page and the API all say the same thing.
 *
 * @phpstan-type TriggerStatusShape array{
 *     delivery: array{mode: string, label: string},
 *     state: string,
 *     label: string,
 *     hint: string|null,
 *     action: string|null,
 *     last_event_at: string|null,
 *     next_check_at: string|null,
 * }
 */
class TriggerStatus
{
    /**
     * @return TriggerStatusShape
     */
    public function for(Trigger $trigger): array
    {
        [$delivery, $state, $label, $hint, $action] = $this->describe($trigger);

        $lastEvent = $trigger->getAttribute('last_event_at') ?? $trigger->events()->max('created_at');

        return [
            'delivery' => $delivery,
            'state' => $state,
            'label' => $label,
            'hint' => $hint,
            'action' => $action,
            'last_event_at' => $lastEvent === null ? null : (string) Carbon::parse($lastEvent)->toIso8601String(),
            'next_check_at' => $this->nextCheckAt($trigger)?->toIso8601String(),
        ];
    }

    /**
     * @return array{0: array{mode: string, label: string}, 1: string, 2: string, 3: string|null, 4: string|null}
     */
    private function describe(Trigger $trigger): array
    {
        $delivery = $this->delivery($trigger);

        if ($trigger->workspace->isSuspended()) {
            return [$delivery, 'suspended', 'Workspace suspended', 'Nothing runs while the workspace is suspended.', null];
        }

        if (! $trigger->is_active) {
            if ($trigger->disabled_reason === TriggerDisabledReason::Storm->value) {
                return [$delivery, 'off_storm', 'Off: too many events', "More than {$trigger->stormLimit()} events arrived in a minute. Check the source, then switch it back on.", 'resume'];
            }

            if ($trigger->consecutive_failure_count >= $trigger->maxFailures()) {
                return [$delivery, 'off_failures', 'Off after failures', 'It failed too many times in a row. Fix the cause, then switch it back on.', 'resume'];
            }

            if ($trigger->consecutive_skip_count >= (int) config('triggers.skips_before_disable')) {
                return [$delivery, 'off_skips', 'Off: runs were dropped', 'Too many events were dropped (no credits, or the last run was still going). Switch it back on when ready.', 'resume'];
            }

            return [$delivery, 'paused', 'Paused', null, 'resume'];
        }

        return match ($trigger->type) {
            TriggerType::Polling => $this->polling($trigger, $delivery),
            TriggerType::Schedule => [$delivery, 'scheduled', 'Scheduled', null, null],
            TriggerType::Webhook => [$delivery, 'listening', 'Listening', null, null],
            TriggerType::Manual => [$delivery, 'manual', 'Run by hand', null, null],
        };
    }

    /**
     * @param  array{mode: string, label: string}  $delivery
     * @return array{0: array{mode: string, label: string}, 1: string, 2: string, 3: string|null, 4: string|null}
     */
    private function polling(Trigger $trigger, array $delivery): array
    {
        if ($this->accountNeedsReconnecting($trigger)) {
            return [$delivery, 'reconnect_needed', 'Reconnect account', 'The connected account has expired or been revoked.', 'reconnect'];
        }

        $subscription = $trigger->subscription;

        // Some events can't be found by looking, only heard: without instant delivery nothing starts it.
        if ($subscription?->status !== SubscriptionStatus::Active && $this->needsPush($trigger)) {
            return [$delivery, 'needs_push', 'Needs instant delivery', 'This trigger only works with instant delivery, which is off or not working, so nothing will start it.', null];
        }

        if ($subscription === null) {
            return [$delivery, 'checking', 'Checking for new items', null, null];
        }

        return match ($subscription->status) {
            SubscriptionStatus::Active => [$delivery, 'listening', 'Listening', $subscription->last_error, null],
            SubscriptionStatus::Pending => [$delivery, 'connecting', 'Connecting', 'Instant delivery is being set up. It keeps checking meanwhile.', null],
            SubscriptionStatus::Degraded => [$delivery, 'degraded', 'Using fallback checks', $subscription->last_error ?? 'Instant delivery is not working right now, so it checks on a timer instead.', null],
            SubscriptionStatus::ReconnectNeeded => [$delivery, 'reconnect_needed', 'Reconnect account', $subscription->last_error ?? 'Reconnect the account to get instant delivery.', 'reconnect'],
            SubscriptionStatus::Failed => [$delivery, 'checking', 'Checking for new items', $subscription->last_error ?? 'Instant delivery is not available for this trigger.', null],
        };
    }

    private function needsPush(Trigger $trigger): bool
    {
        $nodeType = $trigger->config['node_type'] ?? null;

        return is_string($nodeType) && app(NodeRegistry::class)->has($nodeType) && app(NodeRegistry::class)->resolve($nodeType) instanceof NeedsPushDelivery;
    }

    /**
     * @return array{mode: string, label: string}
     */
    private function delivery(Trigger $trigger): array
    {
        return match ($trigger->type) {
            TriggerType::Webhook => ['mode' => 'webhook', 'label' => 'On request'],
            TriggerType::Schedule => ['mode' => 'schedule', 'label' => 'On a schedule'],
            TriggerType::Manual => ['mode' => 'manual', 'label' => 'Run by hand'],
            TriggerType::Polling => $trigger->subscription?->status === SubscriptionStatus::Active
                ? ['mode' => 'instant', 'label' => 'Instant']
                : ['mode' => 'polling', 'label' => 'Checks every '.$this->minutes($trigger->pollIntervalMinutes())],
        };
    }

    /**
     * "5 min", "1 hour".
     */
    private function minutes(int $minutes): string
    {
        return $minutes % 60 === 0 && $minutes >= 60 ? ($minutes / 60).' hour'.($minutes > 60 ? 's' : '') : $minutes.' min';
    }

    /**
     * When the next check happens, for the kinds that check on a timer.
     */
    private function nextCheckAt(Trigger $trigger): ?Carbon
    {
        if (! $trigger->is_active) {
            return null;
        }

        return match ($trigger->type) {
            TriggerType::Schedule => $trigger->next_run_at,
            TriggerType::Polling => $trigger->next_poll_at,
            default => null,
        };
    }

    /**
     * Whether the trigger's account has had its refresh token rejected.
     */
    private function accountNeedsReconnecting(Trigger $trigger): bool
    {
        if ($trigger->credential_id === null) {
            return false;
        }

        $credential = ConnectorCredential::query()->find($trigger->credential_id);

        return $credential !== null && filled($credential->data['refresh_rejected_at'] ?? null);
    }
}
