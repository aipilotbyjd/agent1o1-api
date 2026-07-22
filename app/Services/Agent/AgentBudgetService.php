<?php

namespace App\Services\Agent;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\InternalAgentRun;
use App\Services\AdminAlertService;

/**
 * Cost & rate guardrails (roadmap item 11).
 *
 * Enforces per-agent budgets against the run history agent_runs already tracks:
 *  - a hard per-run token ceiling (max_tokens_per_run),
 *  - a rolling daily token budget,
 *  - a rolling daily spend budget.
 *
 * On breach the agent is auto-paused and its workspace admins are alerted, so a
 * runaway loop can't quietly burn the account.
 */
class AgentBudgetService
{
    /**
     * Approximate USD price per 1M tokens, {input, output}. Unknown models fall
     * back to a conservative default so a budget still means something.
     *
     * @var array<string, array{input: float, output: float}>
     */
    private const PRICING = [
        'claude-opus-4-8' => ['input' => 5.00, 'output' => 25.00],
        'claude-opus-4' => ['input' => 15.00, 'output' => 75.00],
        'claude-sonnet-5' => ['input' => 3.00, 'output' => 15.00],
        'claude-sonnet-4-6' => ['input' => 3.00, 'output' => 15.00],
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00],
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
    ];

    private const DEFAULT_PRICING = ['input' => 3.00, 'output' => 15.00];

    public function __construct(private readonly AdminAlertService $alerts) {}

    /**
     * Decide whether an agent may start a run right now. Returns a human-readable
     * reason when the run must be blocked, or null when it may proceed.
     */
    public function blockReason(Agent $agent): ?string
    {
        if ($agent->is_paused) {
            return $agent->paused_reason ?: 'This agent is paused.';
        }

        if ($agent->daily_token_budget !== null) {
            $usedTokens = $this->tokensUsedToday($agent);
            if ($usedTokens >= $agent->daily_token_budget) {
                return "Daily token budget reached ({$usedTokens}/{$agent->daily_token_budget}).";
            }
        }

        if ($agent->daily_cost_budget !== null) {
            $spent = $this->costSpentToday($agent);
            if ($spent >= (float) $agent->daily_cost_budget) {
                return sprintf('Daily cost budget reached ($%.2f/$%.2f).', $spent, (float) $agent->daily_cost_budget);
            }
        }

        return null;
    }

    /**
     * Estimate the USD cost of a run from its token usage.
     */
    public function estimateCost(?string $model, ?int $promptTokens, ?int $completionTokens): float
    {
        $pricing = self::PRICING[$model] ?? self::DEFAULT_PRICING;

        return round(
            (($promptTokens ?? 0) / 1_000_000) * $pricing['input']
            + (($completionTokens ?? 0) / 1_000_000) * $pricing['output'],
            6,
        );
    }

    /**
     * Persist a run's estimated cost and enforce budgets after it completes.
     * Pauses the agent + alerts admins if a daily budget is now exceeded.
     */
    public function settleRun(Agent $agent, AgentRun $run): void
    {
        $cost = $this->estimateCost($run->model, $run->prompt_tokens, $run->completion_tokens);
        $run->forceFill(['estimated_cost' => $cost])->save();

        if ($agent->is_paused) {
            return;
        }

        $breach = null;

        if ($agent->daily_token_budget !== null) {
            $usedTokens = $this->tokensUsedToday($agent);
            if ($usedTokens >= $agent->daily_token_budget) {
                $breach = "daily token budget ({$usedTokens}/{$agent->daily_token_budget} tokens)";
            }
        }

        if ($breach === null && $agent->daily_cost_budget !== null) {
            $spent = $this->costSpentToday($agent);
            if ($spent >= (float) $agent->daily_cost_budget) {
                $breach = sprintf('daily cost budget ($%.2f/$%.2f)', $spent, (float) $agent->daily_cost_budget);
            }
        }

        if ($breach !== null) {
            $this->pause($agent, "Auto-paused: exceeded {$breach}.");
        }
    }

    /**
     * Whether a run's token usage exceeded the per-run ceiling.
     */
    public function exceededRunCeiling(Agent $agent, ?int $totalTokens): bool
    {
        return $agent->max_tokens_per_run !== null
            && $totalTokens !== null
            && $totalTokens > $agent->max_tokens_per_run;
    }

    public function pause(Agent $agent, string $reason): void
    {
        $agent->forceFill(['is_paused' => true, 'paused_reason' => $reason])->save();

        $agent->loadMissing('workspace');

        if ($agent->workspace) {
            $this->alerts->alertWorkspaceAdmins(
                $agent->workspace,
                "Agent \"{$agent->name}\" paused",
                $reason,
                ['agent_id' => $agent->id, 'reason' => $reason],
            );
        }
    }

    public function resume(Agent $agent): void
    {
        $agent->forceFill(['is_paused' => false, 'paused_reason' => null])->save();
    }

    public function tokensUsedToday(Agent $agent): int
    {
        $own = (int) $agent->runs()
            ->whereDate('created_at', today())
            ->sum('total_tokens');

        return $own + (int) $this->internalRunsToday($agent)->sum('total_tokens');
    }

    public function costSpentToday(Agent $agent): float
    {
        $own = (float) $agent->runs()
            ->whereDate('created_at', today())
            ->sum('estimated_cost');

        return $own + (float) $this->internalRunsToday($agent)->sum('estimated_cost');
    }

    /**
     * Internal-agent calls (planner, reflection, moderation, ...) made today in
     * service of this agent's runs — counted toward its budgets so "system
     * overhead" spend is no longer invisible.
     *
     * @return \Illuminate\Database\Eloquent\Builder<InternalAgentRun>
     */
    private function internalRunsToday(Agent $agent)
    {
        return InternalAgentRun::query()
            ->whereDate('created_at', today())
            ->whereIn('parent_run_id', $agent->runs()->select('id'));
    }
}
