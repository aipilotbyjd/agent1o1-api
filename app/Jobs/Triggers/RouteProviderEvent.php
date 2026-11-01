<?php

namespace App\Jobs\Triggers;

use App\Contracts\Triggers\ItemPushSource;
use App\Contracts\Triggers\PollsOnEvent;
use App\Enums\Triggers\ProviderEventStatus;
use App\Enums\Triggers\SubscriptionStatus;
use App\Enums\Triggers\TriggerType;
use App\Models\Triggers\ProviderEvent;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerSubscription;
use App\Services\Triggers\Push\PushSources;
use App\Services\Triggers\TriggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Hands one stored provider event to every trigger subscribed to it.
 *
 * Each trigger gets the event through the usual intake (TriggerService::
 * receive), so dedupe, filters, credits, caps and the circuit breaker all
 * apply as they do for any other source. One trigger failing never keeps the
 * others from getting the event.
 */
class RouteProviderEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Seconds to wait before each retry.
     *
     * @var list<int>
     */
    public array $backoff = [5, 30];

    public function __construct(public ProviderEvent $event)
    {
        $this->onQueue((string) config('triggers.fire_queue'));
    }

    /**
     * Route the event to the triggers whose subscription matches it.
     */
    public function handle(PushSources $sources, TriggerService $triggers): void
    {
        $this->event->refresh();

        if ($this->event->status !== ProviderEventStatus::Received) {
            return;
        }

        $source = $sources->source($this->event->provider);

        if ($source === null || $this->event->routing_key === null) {
            $this->finish(ProviderEventStatus::Dropped);

            return;
        }

        // The provider is telling us the watch itself needs attention (Graph:
        // reauthorise, subscription removed): renew it now rather than waiting.
        if ($this->event->event_type === 'lifecycle') {
            $this->renewWatchNamedByEvent();
            $this->finish(ProviderEventStatus::Routed);

            return;
        }

        $routed = 0;

        $this->subscriptions()->eachById(function (TriggerSubscription $subscription) use ($source, $triggers, &$routed): void {
            $trigger = $subscription->trigger;

            if ($trigger === null || ! $trigger->is_active || $trigger->workspace->isSuspended()) {
                return;
            }

            try {
                // An event that only signals "look now" (a file was shared) wakes the poll
                // even for a source that otherwise delivers items.
                $delivered = match (true) {
                    $source instanceof PollsOnEvent && $source->wantsPoll($trigger, (array) $this->event->payload) => $this->pokePoll($trigger),
                    $source instanceof ItemPushSource => $this->deliverItem($source, $triggers, $trigger),
                    default => $this->pokePoll($trigger),
                };
            } catch (Throwable $exception) {
                report($exception);

                return;
            }

            if (! $delivered) {
                return;
            }

            // Events are arriving again: whatever made it fall back is over.
            $subscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'last_event_at' => now(),
                'last_ok_at' => now(),
                'last_error' => $subscription->status === SubscriptionStatus::Degraded ? null : $subscription->last_error,
            ])->save();

            $routed++;
        });

        $this->finish($routed > 0 ? ProviderEventStatus::Routed : ProviderEventStatus::Dropped);
    }

    /**
     * Hand the item the event carries to the trigger, if it wants it.
     */
    private function deliverItem(ItemPushSource $source, TriggerService $triggers, Trigger $trigger): bool
    {
        $item = $source->itemFor($this->event->payload);
        $itemId = $source->itemId($item);

        if ($itemId === '' || ! $source->accepts($trigger, $item)) {
            return false;
        }

        $triggers->receive($trigger, TriggerType::Webhook, $item, $itemId);

        return true;
    }

    /**
     * The provider only said something changed: make the trigger look now.
     *
     * The poll does the rest with everything it already knows (filters, order,
     * dedupe). The flag lets a poll that is already running notice it was
     * poked again, and tells the poll this one was prompted by push.
     */
    private function pokePoll(Trigger $trigger): bool
    {
        Cache::put(PollTrigger::pokeKey($trigger), true, now()->addMinutes(10));

        PollTrigger::dispatch($trigger);

        return true;
    }

    /**
     * The subscriptions an event is for: same provider and routing key, and
     * able to receive events.
     *
     * @return Builder<TriggerSubscription>
     */
    private function subscriptions(): Builder
    {
        return TriggerSubscription::query()
            ->where('provider', $this->event->provider)
            ->where('routing_key', $this->event->routing_key)
            ->whereIn('status', array_map(
                fn (SubscriptionStatus $status): string => $status->value,
                array_filter(SubscriptionStatus::cases(), fn (SubscriptionStatus $status): bool => $status->receivesEvents()),
            ))
            ->with('trigger.workspace');
    }

    private function renewWatchNamedByEvent(): void
    {
        ProviderWatch::query()
            ->where('provider', $this->event->provider)
            ->where('external_id', $this->event->routing_key)
            ->each(fn (ProviderWatch $watch) => RenewProviderWatch::dispatch($watch->id));
    }

    private function finish(ProviderEventStatus $status): void
    {
        $this->event->forceFill(['status' => $status, 'processed_at' => now()])->save();
    }

    /**
     * Mark the event failed once every retry is spent.
     */
    public function failed(?Throwable $exception): void
    {
        $this->event->forceFill([
            'status' => ProviderEventStatus::Failed,
            'error' => $exception !== null ? mb_substr($exception->getMessage(), 0, 500) : null,
            'processed_at' => now(),
        ])->save();
    }
}
