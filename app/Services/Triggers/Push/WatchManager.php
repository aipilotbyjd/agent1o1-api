<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\RefreshesWatchForTrigger;
use App\Contracts\Triggers\WatchedTriggerSource;
use App\Enums\Triggers\SubscriptionStatus;
use App\Exceptions\ConnectorException;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Owns the life of provider watches: makes one when the first trigger needs it,
 * shares it with every trigger watching the same thing, renews it before it
 * expires, and removes it after a grace period once nobody uses it.
 *
 * The grace period stops a republish (which removes and re-adds a trigger's
 * subscription) from deleting and recreating the provider's watch each time.
 *
 * @see docs/APP_TRIGGERS_PLAN.md "Control plane"
 */
class WatchManager
{
    /**
     * Minutes an unused watch is kept before it is removed.
     */
    private const int GRACE_MINUTES = 10;

    /**
     * The watch the trigger needs, made if it does not exist yet.
     *
     * @throws ConnectorException when the account can't be used
     */
    public function acquire(WatchedTriggerSource $source, Trigger $trigger): ProviderWatch
    {
        $key = $source->watchKeyFor($trigger) ?? throw new ConnectorException('There is nothing to watch for this trigger.');

        return Cache::lock($this->lockName($source->provider(), $trigger->credential_id, $key), 60)->block(30, function () use ($source, $trigger, $key): ProviderWatch {
            $watch = ProviderWatch::query()->firstOrNew([
                'provider' => $source->provider(),
                'credential_id' => $trigger->credential_id,
                'resource_key' => $key,
            ]);

            $watch->workspace_id = $trigger->workspace_id;
            $watch->release_at = null;

            $needsCreating = ! $watch->exists
                || $watch->external_id === null
                || in_array($watch->status, [SubscriptionStatus::Failed, SubscriptionStatus::ReconnectNeeded], true);

            if ($needsCreating) {
                $source->createWatch($watch, $trigger);
                $watch->status = SubscriptionStatus::Active;
                $watch->last_ok_at = now();
                $watch->last_error = null;
            } elseif ($source instanceof RefreshesWatchForTrigger && $source->watchIsStale($watch, $trigger)) {
                // The shared watch doesn't cover what this trigger needs (another label): widen it.
                $source->renewWatch($watch);
                $watch->last_ok_at = now();
            }

            $watch->save();

            return $watch;
        });
    }

    /**
     * Start the grace period for the watch behind a subscription that is going
     * away, if nothing else uses it.
     */
    public function release(TriggerSubscription $subscription): void
    {
        $watch = $subscription->watch;

        if ($watch === null) {
            return;
        }

        $stillUsed = $watch->subscriptions()->whereKeyNot($subscription->getKey())->exists();

        if (! $stillUsed) {
            $watch->forceFill(['release_at' => now()->addMinutes(self::GRACE_MINUTES)])->save();
        }
    }

    /**
     * Extend the watch, and tell its subscriptions how it went.
     *
     * A failure is recorded and left for the next renewal run to retry; the
     * watch only drops to degraded (polling covers for it) once it has actually
     * expired, or an account problem means it can't be renewed at all.
     */
    public function renew(WatchedTriggerSource $source, ProviderWatch $watch): void
    {
        Cache::lock($this->lockName($watch->provider, $watch->credential_id, $watch->resource_key), 60)->block(30, function () use ($source, $watch): void {
            $watch->refresh();

            try {
                $source->renewWatch($watch);

                $watch->forceFill(['status' => SubscriptionStatus::Active, 'last_ok_at' => now(), 'last_error' => null])->save();
            } catch (ConnectorException $exception) {
                $watch->forceFill(['status' => SubscriptionStatus::ReconnectNeeded, 'last_error' => $this->short($exception)])->save();
            } catch (Throwable $exception) {
                Log::warning('Provider watch renewal failed.', ['watch_id' => $watch->id, 'provider' => $watch->provider, 'error' => $exception->getMessage()]);

                $expired = $watch->expires_at !== null && $watch->expires_at->isPast();

                $watch->forceFill([
                    'status' => $expired ? SubscriptionStatus::Degraded : $watch->status,
                    'last_error' => $this->short($exception),
                ])->save();
            }

            $this->propagate($source, $watch);
        });
    }

    /**
     * Remove the provider-side registration and the row.
     */
    public function remove(WatchedTriggerSource $source, ProviderWatch $watch): void
    {
        try {
            $source->deleteWatch($watch);
        } catch (Throwable $exception) {
            Log::info('Provider watch could not be removed at the provider.', ['watch_id' => $watch->id, 'error' => $exception->getMessage()]);
        }

        $watch->delete();
    }

    /**
     * Copy the watch's state to the subscriptions that use it: their status,
     * and the routing key (which a renewal can change).
     */
    public function propagate(WatchedTriggerSource $source, ProviderWatch $watch): void
    {
        $watch->subscriptions()->with('trigger')->get()->each(function (TriggerSubscription $subscription) use ($source, $watch): void {
            if ($subscription->trigger === null) {
                return;
            }

            $subscription->forceFill([
                'status' => $watch->status,
                'routing_key' => $source->routingKeyFor($subscription->trigger, $watch) ?? $subscription->routing_key,
                'last_error' => $watch->last_error,
                'last_ok_at' => $watch->status === SubscriptionStatus::Active ? now() : $subscription->last_ok_at,
            ])->save();
        });
    }

    private function lockName(string $provider, ?string $credentialId, string $key): string
    {
        return "provider-watch:{$provider}:{$credentialId}:".md5($key);
    }

    private function short(Throwable $exception): string
    {
        return mb_substr($exception->getMessage(), 0, 500);
    }
}
