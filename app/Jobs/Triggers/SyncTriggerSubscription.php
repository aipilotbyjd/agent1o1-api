<?php

namespace App\Jobs\Triggers;

use App\Contracts\Triggers\PushTriggerSource;
use App\Contracts\Triggers\WatchedTriggerSource;
use App\Enums\Triggers\SubscriptionStatus;
use App\Enums\Triggers\TriggerType;
use App\Exceptions\ConnectorException;
use App\Exceptions\WatchRefusedException;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerSubscription;
use App\Services\Triggers\Push\PushSources;
use App\Services\Triggers\Push\WatchManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Brings one trigger's push subscription in line with what the trigger now
 * wants: make it, correct it, or remove it.
 *
 * Publishing and switching a trigger on or off only queue this; they never
 * wait on a provider. Safe to run again at any time, and one at a time per
 * trigger.
 *
 * @see docs/APP_TRIGGERS_PLAN.md "Desired-state reconciler"
 */
class SyncTriggerSubscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Seconds to wait before each retry.
     *
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(public string $triggerId)
    {
        $this->onQueue((string) config('triggers.fire_queue'));
    }

    /**
     * One reconcile per trigger at a time.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->triggerId))->releaseAfter(30)->expireAfter(120)];
    }

    /**
     * Make the subscription match the trigger.
     */
    public function handle(PushSources $sources, WatchManager $watches): void
    {
        $trigger = Trigger::query()->find($this->triggerId);

        if ($trigger === null) {
            return;
        }

        $existing = TriggerSubscription::query()->where('trigger_id', $this->triggerId)->first();

        $source = $trigger->type === TriggerType::Polling && $trigger->is_active
            ? $sources->forTrigger($trigger)
            : null;

        if ($source === null) {
            if ($existing !== null) {
                $existing->delete();
                $watches->release($existing);
            }

            return;
        }

        $watch = null;

        try {
            if ($source instanceof WatchedTriggerSource) {
                $watch = $watches->acquire($source, $trigger);
            }

            $routingKey = $source->routingKeyFor($trigger, $watch);
        } catch (ConnectorException $exception) {
            $this->record($trigger, $source->provider(), SubscriptionStatus::ReconnectNeeded, $exception->getMessage());

            return;
        } catch (WatchRefusedException $exception) {
            // Refused in words, and a repeat would be refused the same way.
            $this->record($trigger, $source->provider(), SubscriptionStatus::Failed, "Instant delivery is not available here. {$exception->getMessage()} It checks on a timer instead.");

            return;
        } catch (RequestException $exception) {
            $this->recordRequestFailure($trigger, $source->provider(), $exception);

            if ($this->isPermanent($exception)) {
                return;
            }

            throw $exception;
        } catch (Throwable $exception) {
            // Out of reach for now (a network blip): the trigger keeps polling.
            $this->record($trigger, $source->provider(), SubscriptionStatus::Pending, $exception->getMessage());

            throw $exception;
        }

        if ($routingKey === null) {
            $this->record($trigger, $source->provider(), SubscriptionStatus::Failed, 'The connected account is not available.');

            return;
        }

        $subscription = TriggerSubscription::query()->updateOrCreate(
            ['trigger_id' => $trigger->id],
            [
                'workspace_id' => $trigger->workspace_id,
                'watch_id' => $watch?->id,
                'provider' => $source->provider(),
                'mechanism' => 'push',
                'routing_key' => $routingKey,
                'status' => $watch?->status ?? SubscriptionStatus::Active,
                'last_ok_at' => now(),
                'last_error' => $this->warningFor($source, $trigger),
            ],
        );

        // Moved to another watch (another folder, say): the old one may be unused now.
        if ($existing !== null && $existing->watch_id !== null && $existing->watch_id !== $subscription->watch_id) {
            $watches->release($existing);
        }
    }

    /**
     * What is wrong with an otherwise working subscription, if the source can
     * tell (the app is not in the channel).
     */
    private function warningFor(PushTriggerSource $source, Trigger $trigger): ?string
    {
        try {
            return $source->accessWarning($trigger);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A provider refusing the request. Unauthorised means the account needs
     * reconnecting; forbidden, missing or unprocessable will not get better by
     * retrying (for example no admin rights on the repository), so the trigger
     * just keeps polling and the reason is shown.
     */
    private function recordRequestFailure(Trigger $trigger, string $provider, RequestException $exception): void
    {
        $status = $exception->response->status();

        $this->record(
            $trigger,
            $provider,
            $status === 401 ? SubscriptionStatus::ReconnectNeeded : ($this->isPermanent($exception) ? SubscriptionStatus::Failed : SubscriptionStatus::Pending),
            $status === 401
                ? 'The connected account was refused. Reconnect it.'
                : ($this->isPermanent($exception)
                    ? "Instant delivery is not available here (the provider answered {$status}). It checks on a timer instead."
                    : $exception->getMessage()),
        );
    }

    private function isPermanent(RequestException $exception): bool
    {
        return in_array($exception->response->status(), [400, 401, 403, 404, 422], true);
    }

    /**
     * Keep the subscription row, with the reason it isn't receiving events.
     */
    private function record(Trigger $trigger, string $provider, SubscriptionStatus $status, string $error): void
    {
        TriggerSubscription::query()->updateOrCreate(
            ['trigger_id' => $trigger->id],
            [
                'workspace_id' => $trigger->workspace_id,
                'provider' => $provider,
                'mechanism' => 'push',
                'routing_key' => TriggerSubscription::query()->where('trigger_id', $trigger->id)->value('routing_key') ?? '',
                'status' => $status,
                'last_error' => mb_substr($error, 0, 500),
            ],
        );
    }
}
