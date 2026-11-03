<?php

namespace App\Services\Billing;

use App\Enums\Billing\CreditGrantSource;
use App\Enums\Notifications\AlertSeverity;
use App\Enums\RunStatus;
use App\Exceptions\CostLimitReachedException;
use App\Exceptions\RunBudgetExceededException;
use App\Models\Ai\AiCallEvent;
use App\Models\Billing\CreditGrant;
use App\Models\Billing\CreditGrantAllocation;
use App\Models\Billing\CreditTransaction;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use App\Services\Notifications\AdminAlerts;
use Carbon\CarbonInterface;

/**
 * AI cost guardrails: limits in dollars and in concurrency that stop work
 * before it starts, on top of the credit balance.
 *
 * - The plan's `ai_cost_daily_usd` / `ai_cost_weekly_usd`: what one
 *   workspace may spend in a UTC day or in 7 days.
 * - The plan's `max_concurrent_runs`: workflow runs in flight at once.
 * - The plan's `max_run_credits`: what one workflow run may spend.
 * - `billing.guardrails.free_tier_daily_budget_usd`: once free and trial
 *   credits spent today, across every workspace, pass it, free and trial
 *   workspaces pause until tomorrow. Paying workspaces never pause.
 * - `SpendControls`: the operator's kill switch, the platform's daily
 *   provider budget and hard stop, and per-workspace throttles.
 *
 * Spend is measured from the credit ledger at `billing.credit_value_usd` per
 * credit, which includes the platform's margin: the caps trigger slightly
 * early rather than late, and read fresh data, unlike the hourly model-usage
 * rollup.
 */
class CostGuard
{
    public function __construct(
        private readonly BillingSettings $settings,
        private readonly AdminAlerts $alerts,
        private readonly CreditMeter $meter,
        private readonly SpendControls $spendControls,
    ) {}

    /**
     * @throws CostLimitReachedException
     */
    public function assertCanStart(Workspace $workspace): void
    {
        if (! $this->enabled()) {
            return;
        }

        $plan = $workspace->currentPlan();

        if ($plan !== null) {
            $this->assertUnder($workspace, 'ai_cost_daily', $plan->aiCostDailyUsd(), now()->startOfDay(), 'today');
            $this->assertUnder($workspace, 'ai_cost_weekly', $plan->aiCostWeeklyUsd(), now()->subDays(7), 'in the last 7 days');
        }

        $onFreeOrTrial = $this->onFreeOrTrialAllowance($workspace);

        $this->assertFreeTierOpen($workspace, $onFreeOrTrial);
        $this->spendControls->assertAllowed($workspace, $onFreeOrTrial);
    }

    /**
     * @throws CostLimitReachedException
     */
    public function assertCanStartWorkflowRun(Workspace $workspace): void
    {
        if (! $this->enabled()) {
            return;
        }

        $max = $workspace->currentPlan()?->maxConcurrentRuns();

        if ($max === null) {
            return;
        }

        $inFlight = Run::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', [RunStatus::Pending, RunStatus::Running])
            ->count();

        if ($inFlight >= $max) {
            throw new CostLimitReachedException('max_concurrent_runs', "Your plan runs up to {$max} workflows at a time. Wait for one to finish or upgrade your plan.");
        }
    }

    /**
     * Stops a run that has used its plan's per-run credit budget, before
     * its next node starts.
     *
     * @throws RunBudgetExceededException
     */
    public function assertRunWithinBudget(Run $run): void
    {
        if (! $this->enabled()) {
            return;
        }

        $max = $run->workspace?->currentPlan()?->maxRunCredits();

        if ($max === null) {
            return;
        }

        $used = $this->creditsUsedBy($run);

        if ($used >= $max) {
            throw new RunBudgetExceededException($max, $used);
        }
    }

    /**
     * Credits paid by free and trial grants today, across every workspace,
     * in dollars.
     */
    public function freeTierSpendTodayUsd(): float
    {
        $credits = (int) CreditGrantAllocation::query()
            ->join('credit_grants', 'credit_grants.id', '=', 'credit_grant_allocations.credit_grant_id')
            ->whereIn('credit_grants.source', [CreditGrantSource::Free->value, CreditGrantSource::Trial->value])
            ->where('credit_grant_allocations.created_at', '>=', now()->startOfDay())
            ->sum('credit_grant_allocations.credits');

        return $credits * $this->creditValueUsd();
    }

    /**
     * What the workspace has spent since `$since`: the larger of its credits
     * at `billing.credit_value_usd` and what its calls actually cost the
     * platform (`ai_call_events`), so a model whose price is missing or wrong
     * cannot slip past the caps by being charged too few credits.
     */
    public function spentSinceUsd(Workspace $workspace, CarbonInterface $since): float
    {
        $credits = (int) CreditTransaction::query()
            ->where('workspace_id', $workspace->id)
            ->where('created_at', '>=', $since)
            ->sum('credits');

        $providerCost = (float) AiCallEvent::query()
            ->where('workspace_id', $workspace->id)
            ->where('occurred_at', '>=', $since)
            ->where('is_byok', false)
            ->sum('provider_cost_usd');

        return max($credits * $this->creditValueUsd(), $providerCost);
    }

    /**
     * The run's running total (`runs.credits_accrued`, added to as each step
     * finishes). A run started before that column existed is re-priced from
     * its steps instead.
     */
    private function creditsUsedBy(Run $run): int
    {
        $accrued = (int) Run::query()->whereKey($run->id)->value('credits_accrued');

        if ($accrued > 0) {
            return $accrued;
        }

        return (int) $run->nodeRuns()
            ->whereNotNull('finished_at')
            ->get(['id', 'run_id', 'type', 'usage'])
            ->sum(fn (NodeRun $nodeRun): int => $this->meter->costForNodeRun($nodeRun));
    }

    private function assertUnder(Workspace $workspace, string $limit, ?float $capUsd, CarbonInterface $since, string $window): void
    {
        if ($capUsd === null) {
            return;
        }

        if ($this->spentSinceUsd($workspace, $since) >= $capUsd) {
            throw new CostLimitReachedException($limit, sprintf(
                'This workspace reached its plan\'s AI usage limit of $%.2f %s. It resets automatically, or upgrade your plan for a higher limit.',
                $capUsd,
                $window,
            ));
        }
    }

    private function assertFreeTierOpen(Workspace $workspace, bool $onFreeOrTrial): void
    {
        $budget = $this->settings->get('guardrails.free_tier_daily_budget_usd');

        if (! is_numeric($budget) || (float) $budget <= 0 || ! $onFreeOrTrial) {
            return;
        }

        $spent = $this->freeTierSpendTodayUsd();

        if ($spent < (float) $budget) {
            return;
        }

        $this->alerts->raise(
            key: 'usage.free_tier_paused',
            title: 'Free and trial usage paused for today',
            body: sprintf('Free and trial credits cost $%.2f today, past the $%.2f daily budget. Paying workspaces are not affected.', $spent, (float) $budget),
            context: ['spent_usd' => round($spent, 2), 'budget_usd' => (float) $budget],
            severity: AlertSeverity::Critical,
            throttleKey: 'usage.free_tier_paused:'.now()->toDateString(),
        );

        throw new CostLimitReachedException('free_tier_daily_budget', 'Free and trial usage is paused for today because of high demand. It resumes tomorrow, or choose a plan to continue now.');
    }

    public function onFreeOrTrialAllowance(Workspace $workspace): bool
    {
        $period = $workspace->currentUsagePeriod();

        return CreditGrant::query()
            ->where('usage_period_id', $period->id)
            ->whereIn('source', [CreditGrantSource::Free, CreditGrantSource::Trial])
            ->whereNull('revoked_at')
            ->exists();
    }

    private function creditValueUsd(): float
    {
        return (float) config('billing.credit_value_usd', 0.005);
    }

    private function enabled(): bool
    {
        return (bool) $this->settings->get('guardrails.enabled', true);
    }
}
