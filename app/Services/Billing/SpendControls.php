<?php

namespace App\Services\Billing;

use App\Enums\Billing\SpendKillSwitch;
use App\Enums\Notifications\AlertSeverity;
use App\Exceptions\CostLimitReachedException;
use App\Models\Ai\AiCallEvent;
use App\Models\Workspaces\Workspace;
use App\Services\Notifications\AdminAlerts;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Platform-wide limits on what AI work may cost, read from real provider
 * spend in `ai_call_events`, on top of each workspace's own caps:
 *
 * - the operator's kill switch (`guardrails.kill_switch`): stop free and
 *   trial workspaces, or everyone, at once;
 * - the platform's daily budget (`guardrails.platform_daily_budget_usd`):
 *   free and trial workspaces stop once today's provider spend reaches it,
 *   and everyone stops at `guardrails.platform_hard_stop_ratio` times it, so
 *   a runaway day has a ceiling even when nobody is watching;
 * - per-workspace throttles, set by an operator or automatically by
 *   `SpendGuard` when a free or trial workspace spikes, that pause one
 *   workspace until a set time.
 */
class SpendControls
{
    private const string SPEND_CACHE_KEY = 'spend-controls:platform-today';

    private const int SPEND_CACHE_SECONDS = 60;

    public function __construct(
        private readonly BillingSettings $settings,
        private readonly AdminAlerts $alerts,
    ) {}

    /**
     * @throws CostLimitReachedException
     */
    public function assertAllowed(Workspace $workspace, bool $onFreeOrTrial): void
    {
        $switch = $this->killSwitch();

        if ($switch === SpendKillSwitch::Everyone || ($switch === SpendKillSwitch::FreeAndTrial && $onFreeOrTrial)) {
            throw new CostLimitReachedException('kill_switch', 'AI features are paused for a short time. Please try again later.');
        }

        if (($until = $this->throttledUntil($workspace->id)) !== null) {
            throw new CostLimitReachedException('workspace_throttled', sprintf(
                'AI usage for this workspace is paused until %s UTC because of unusual activity. Contact support if this is unexpected.',
                $until->format('M j, H:i'),
            ));
        }

        $budget = $this->platformDailyBudgetUsd();

        if ($budget === null) {
            return;
        }

        $spent = $this->platformSpendTodayUsd();

        if ($spent >= $budget * $this->hardStopRatio()) {
            $this->raise('usage.platform_hard_stop', 'Platform AI spend hit its hard stop', $spent, $budget, AlertSeverity::Critical);

            throw new CostLimitReachedException('platform_hard_stop', 'AI features are paused for today because of very high demand. They resume tomorrow.');
        }

        if ($onFreeOrTrial && $spent >= $budget) {
            $this->raise('usage.platform_budget', 'Platform AI spend reached today\'s budget', $spent, $budget, AlertSeverity::Warning);

            throw new CostLimitReachedException('platform_daily_budget', 'Free and trial usage is paused for today because of high demand. It resumes tomorrow, or choose a plan to continue now.');
        }
    }

    public function killSwitch(): SpendKillSwitch
    {
        return SpendKillSwitch::tryFrom((string) $this->settings->get('guardrails.kill_switch', SpendKillSwitch::Off->value)) ?? SpendKillSwitch::Off;
    }

    public function setKillSwitch(SpendKillSwitch $switch): void
    {
        $this->settings->set('guardrails.kill_switch', $switch->value);
    }

    public function platformDailyBudgetUsd(): ?float
    {
        $budget = $this->settings->get('guardrails.platform_daily_budget_usd');

        return is_numeric($budget) && (float) $budget > 0 ? (float) $budget : null;
    }

    public function hardStopRatio(): float
    {
        return max(1.0, (float) $this->settings->get('guardrails.platform_hard_stop_ratio', 1.5));
    }

    /**
     * What the platform has paid providers today (UTC), from the call
     * ledger; calls on a workspace's own key cost the platform nothing.
     * Cached for a minute: the check runs before every piece of work.
     */
    public function platformSpendTodayUsd(): float
    {
        return (float) Cache::remember(self::SPEND_CACHE_KEY.':'.now()->toDateString(), self::SPEND_CACHE_SECONDS, fn (): float => (float) AiCallEvent::query()
            ->where('occurred_at', '>=', now()->startOfDay())
            ->where('is_byok', false)
            ->sum('provider_cost_usd'));
    }

    public function throttle(string $workspaceId, CarbonInterface $until, string $reason): void
    {
        Cache::put($this->throttleKey($workspaceId), ['until' => $until->toIso8601String(), 'reason' => $reason], $until);
    }

    public function unthrottle(string $workspaceId): void
    {
        Cache::forget($this->throttleKey($workspaceId));
    }

    public function throttledUntil(string $workspaceId): ?CarbonInterface
    {
        $throttle = Cache::get($this->throttleKey($workspaceId));

        if (! is_array($throttle) || ! isset($throttle['until'])) {
            return null;
        }

        $until = now()->parse($throttle['until']);

        return $until->isFuture() ? $until : null;
    }

    /**
     * The workspace's current throttle, if any.
     *
     * @return array{until: string, reason: string}|null
     */
    public function currentThrottle(string $workspaceId): ?array
    {
        $throttle = Cache::get($this->throttleKey($workspaceId));

        return is_array($throttle) && $this->throttledUntil($workspaceId) !== null ? $throttle : null;
    }

    private function throttleKey(string $workspaceId): string
    {
        return "spend-controls:throttle:{$workspaceId}";
    }

    private function raise(string $key, string $title, float $spent, float $budget, AlertSeverity $severity): void
    {
        $this->alerts->raise(
            key: $key,
            title: $title,
            body: sprintf('Provider spend today is $%.2f against a $%.2f daily budget.', $spent, $budget),
            context: ['spent_usd' => round($spent, 2), 'budget_usd' => $budget],
            severity: $severity,
            throttleKey: $key.':'.now()->toDateString(),
        );
    }
}
