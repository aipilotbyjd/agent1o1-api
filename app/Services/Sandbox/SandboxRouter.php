<?php

namespace App\Services\Sandbox;

use App\Enums\Sandbox\SandboxMode;
use App\Models\Sandbox\SandboxProvider;
use Illuminate\Support\Collection;

/**
 * Picks which provider takes a piece of work. In order: the kill switch and
 * monthly budget (either stops everything), then only providers that are
 * enabled, set up, allowed for the mode, outside an open circuit and under
 * their concurrency limit. The default provider goes first, then lower
 * priority numbers; providers tied on priority share the work by weight.
 */
class SandboxRouter
{
    public function __construct(
        private readonly SandboxProviders $providers,
        private readonly SandboxSettings $settings,
        private readonly SandboxConcurrency $concurrency,
        private readonly SandboxRunLog $log,
    ) {}

    /**
     * Providers to try, best first. Empty means code cannot run right now.
     *
     * @return Collection<int, SandboxProvider>
     */
    public function candidates(SandboxMode $mode): Collection
    {
        if ($this->stopReason() !== null) {
            return collect();
        }

        $usable = $this->providers->usable()->filter(
            fn (SandboxProvider $provider): bool => $provider->allowsMode($mode)
                && ! $provider->isCircuitOpen()
                && $this->concurrency->hasRoom($provider),
        );

        return $usable
            ->groupBy(fn (SandboxProvider $provider): string => ($provider->is_default ? '0' : '1').':'.str_pad((string) $provider->priority, 6, '0', STR_PAD_LEFT))
            ->sortKeys()
            ->flatMap(fn (Collection $group): Collection => $this->weightedOrder($group))
            ->values();
    }

    public function available(SandboxMode $mode): bool
    {
        return $this->candidates($mode)->isNotEmpty();
    }

    /**
     * Why nothing may run at all, or null when work is allowed.
     */
    public function stopReason(): ?string
    {
        if ($this->settings->killed()) {
            return 'Running code is switched off right now.';
        }

        $budget = $this->settings->monthlyBudgetUsd();

        if ($budget !== null && $this->log->monthCostUsd() >= $budget) {
            return 'Running code has reached its limit for this month.';
        }

        return null;
    }

    /**
     * A random order where a provider with twice the weight comes first
     * twice as often.
     *
     * @param  Collection<int, SandboxProvider>  $group
     * @return Collection<int, SandboxProvider>
     */
    private function weightedOrder(Collection $group): Collection
    {
        if ($group->count() < 2) {
            return $group->values();
        }

        return $group
            ->sortBy(fn (SandboxProvider $provider): float => -log(random_int(1, 1_000_000) / 1_000_001) / max(1, $provider->weight))
            ->values();
    }
}
