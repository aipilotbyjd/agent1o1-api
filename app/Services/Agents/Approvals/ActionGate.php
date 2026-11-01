<?php

namespace App\Services\Agents\Approvals;

use App\Enums\Agents\ActionEffect;
use App\Enums\Agents\ActionRisk;
use App\Enums\Agents\ActionVerdict;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\Agents\AgentPlanStatus;
use App\Enums\Agents\AutonomyMode;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentPlan;
use Illuminate\Support\Str;

/**
 * Decides what happens to one tool call: allow, ask, deny or simulate, and
 * records the call as an `AgentAction` (every call but a read that runs
 * freely, which would only be noise).
 *
 * The order, from `decide()`:
 *
 * 1. Read-only mode refuses every write outright. Nothing loosens it.
 * 2. The tool's own rule: the first of its `conditions` that matches, else
 *    its `mode` (`allow`/`ask`/`deny`). Either replaces the mode's verdict,
 *    which is how "always allow this tool" works, except that a rule can't
 *    let a write run more freely than the workspace's `max_autonomy_mode`
 *    would (`ceilingVerdict()`).
 * 3. Otherwise the mode decides: see `modeVerdict()`.
 * 4. The tool's `rate_limit`, a tool that keeps failing in this turn, then
 *    the workspace's guardrails. These only ever make the verdict stricter:
 *    no agent setting can get past them.
 * 5. Test run turns anything that would run or ask into a simulation, and so
 *    does a context that can't pause for a person (`ActionContext::$canPause`).
 *
 * A call that was already weighed (the SDK weighs a call again when a paused
 * turn resumes) gets its recorded decision back, so the answer can't change
 * between the pause and the resume, and a Smart-mode review is never paid
 * for twice.
 */
class ActionGate
{
    /** Failed runs of one tool in a turn before further calls are refused. */
    private const int MAX_FAILURES_PER_TURN = 3;

    public function __construct(
        private readonly ConditionEvaluator $conditions,
        private readonly ActionReviewer $reviewer,
    ) {}

    public function weigh(ActionContext $context, ToolCall $call): GateDecision
    {
        if ($call->toolCallId !== null && ($existing = $this->existing($context, $call->toolCallId)) !== null) {
            return GateDecision::fromAction($existing);
        }

        $decision = $this->decide($context, $call);

        if ($decision['verdict'] === ActionVerdict::Allow && $call->effect->isRead()) {
            return new GateDecision(ActionVerdict::Allow, $decision['reason']);
        }

        return GateDecision::fromAction($this->record($context, $call, $decision));
    }

    /**
     * @return array{verdict: ActionVerdict, reason: array<string, mixed>, risk?: ActionRisk|null, review?: array<string, mixed>|null, plan_id?: string|null}
     */
    private function decide(ActionContext $context, ToolCall $call): array
    {
        if ($context->mode === AutonomyMode::ReadOnly && ! $call->effect->isRead()) {
            return $this->verdict(ActionVerdict::Deny, 'mode', 'This agent is read-only: it can look things up but not change anything.');
        }

        $rule = $this->ruleVerdict($call);
        $decision = $rule === null ? $this->modeVerdict($context, $call) : $this->tighten($rule, $this->ceilingVerdict($context, $call, $rule));

        $decision = $this->tighten($decision, $this->rateLimitVerdict($context, $call));
        $decision = $this->tighten($decision, $this->repeatedFailureVerdict($context, $call));

        foreach ($this->guardrailVerdicts($context, $call) as $guardrail) {
            $decision = $this->tighten($decision, $guardrail);
        }

        if ($call->effect->isRead() || ! in_array($decision['verdict'], [ActionVerdict::Allow, ActionVerdict::Ask], true)) {
            return $decision;
        }

        if ($context->testMode) {
            return [...$decision, ...$this->verdict(ActionVerdict::Simulate, 'test_run', 'Test run is on, so this was simulated and nothing was really done.')];
        }

        if ($decision['verdict'] === ActionVerdict::Ask && ! $context->canPause) {
            return [...$decision, ...$this->verdict(ActionVerdict::Simulate, 'context', 'This needs approval, and there is no conversation here to pause, so it was simulated instead.')];
        }

        return $decision;
    }

    /**
     * @return array{verdict: ActionVerdict, reason: array<string, mixed>}|null
     */
    private function ruleVerdict(ToolCall $call): ?array
    {
        $policy = $call->policy ?? [];

        foreach ($policy['conditions'] ?? [] as $condition) {
            $then = ActionVerdict::tryFrom((string) ($condition['then'] ?? ''));

            if ($then !== null && $this->conditions->matches($condition, $call->effectiveArguments)) {
                return $this->verdict($then, 'condition', $condition['label'] ?? $this->describeCondition($condition));
            }
        }

        $mode = ActionVerdict::tryFrom((string) ($policy['mode'] ?? ''));

        if ($mode === null || $mode === ActionVerdict::Simulate) {
            return null;
        }

        return $this->verdict($mode, 'tool_rule', match ($mode) {
            ActionVerdict::Allow => 'This tool is set to run without asking.',
            ActionVerdict::Ask => 'This tool is set to always ask first.',
            default => 'This tool is set to never run.',
        });
    }

    /**
     * What the workspace's `max_autonomy_mode` would make of a write a tool
     * rule lets run without asking: the admin's cap is a ceiling no agent
     * setting gets past, "always allow" included. Null when there is no cap
     * or the rule doesn't loosen anything.
     *
     * @param  array{verdict: ActionVerdict}  $rule
     * @return array{verdict: ActionVerdict, reason: array<string, mixed>, risk?: ActionRisk|null, review?: array<string, mixed>|null, plan_id?: string|null}|null
     */
    private function ceilingVerdict(ActionContext $context, ToolCall $call, array $rule): ?array
    {
        $cap = $context->policy->max_autonomy_mode;

        if ($cap === null || $call->effect->isRead() || $rule['verdict'] !== ActionVerdict::Allow) {
            return null;
        }

        // A Plan cap on a conversation not in Plan mode (a stricter mode won)
        // has no `submit_plan` to offer, so the call asks instead.
        if ($cap === AutonomyMode::Plan && $context->mode !== AutonomyMode::Plan) {
            $cap = AutonomyMode::Ask;
        }

        return $this->modeVerdict(new ActionContext(
            agent: $context->agent,
            run: $context->run,
            session: $context->session,
            mode: $cap,
            testMode: $context->testMode,
            canPause: $context->canPause,
            policy: $context->policy,
        ), $call);
    }

    /**
     * @return array{verdict: ActionVerdict, reason: array<string, mixed>, risk?: ActionRisk|null, review?: array<string, mixed>|null, plan_id?: string|null}
     */
    private function modeVerdict(ActionContext $context, ToolCall $call): array
    {
        if ($call->effect->isRead()) {
            return $this->verdict(ActionVerdict::Allow, 'mode', 'Looking things up never needs approval.');
        }

        return match ($context->mode) {
            AutonomyMode::ReadOnly => $this->verdict(ActionVerdict::Deny, 'mode', 'This agent is read-only.'),
            AutonomyMode::Ask => $this->verdict(ActionVerdict::Ask, 'mode', 'In Ask mode every action that changes something needs approval.'),
            AutonomyMode::Plan => $this->planVerdict($context, $call),
            AutonomyMode::Smart => $this->smartVerdict($context, $call),
            AutonomyMode::Autopilot => $call->effect === ActionEffect::Destructive && ! $context->policy->allow_destructive_in_autopilot
                ? $this->verdict(ActionVerdict::Ask, 'mode', 'Destructive actions always need approval, even on Autopilot.')
                : $this->verdict(ActionVerdict::Allow, 'mode', 'Autopilot runs actions without asking.'),
        };
    }

    /**
     * @return array{verdict: ActionVerdict, reason: array<string, mixed>, plan_id?: string|null}
     */
    private function planVerdict(ActionContext $context, ToolCall $call): array
    {
        $plan = $context->session?->plans()
            ->where('status', AgentPlanStatus::Approved)
            ->latest('id')
            ->first();

        if ($plan === null) {
            return $this->verdict(ActionVerdict::Deny, 'plan', 'Plan mode: propose a plan with submit_plan and wait for it to be approved before taking actions.');
        }

        $step = $this->matchingStep($plan, $call);

        if ($step === null) {
            return [
                ...$this->verdict(ActionVerdict::Ask, 'plan', 'This action is not part of the approved plan.'),
                'plan_id' => $plan->id,
            ];
        }

        return [
            'verdict' => ActionVerdict::Allow,
            'reason' => ['source' => 'plan', 'detail' => "Step of the approved plan: {$step['summary']}", 'step_id' => $step['id']],
            'plan_id' => $plan->id,
        ];
    }

    /**
     * The first pending step for this tool whose committed arguments all
     * match the call: compared loosely (trimmed, case-insensitive), since
     * the model restates them rather than copying them.
     *
     * A step already taken by a call that is running or ran doesn't count
     * as pending: a step is ticked off only once its call finishes, and
     * without this, two calls made in the same turn could both run on the
     * strength of one approved step.
     *
     * @return array<string, mixed>|null
     */
    private function matchingStep(AgentPlan $plan, ToolCall $call): ?array
    {
        $taken = $plan->actions()
            ->whereIn('status', [AgentActionStatus::Running, AgentActionStatus::Executed])
            ->get()
            ->map(fn (AgentAction $action): ?string => $action->reason['step_id'] ?? null)
            ->filter()
            ->all();

        foreach ($plan->pendingSteps() as $step) {
            if (($step['tool'] ?? null) !== $call->toolName || in_array($step['id'], $taken, true)) {
                continue;
            }

            $matches = collect($step['arguments'] ?? [])->every(fn (mixed $expected, string $key): bool => $this->loosely(data_get($call->effectiveArguments, $key)) === $this->loosely($expected));

            if ($matches) {
                return $step;
            }
        }

        return null;
    }

    private function loosely(mixed $value): string
    {
        return mb_strtolower(trim(is_scalar($value) || $value === null ? (string) $value : (string) json_encode($value)));
    }

    /**
     * @return array{verdict: ActionVerdict, reason: array<string, mixed>, risk: ActionRisk, review: array<string, mixed>}
     */
    private function smartVerdict(ActionContext $context, ToolCall $call): array
    {
        if ($call->effect === ActionEffect::Destructive) {
            return [
                ...$this->verdict(ActionVerdict::Ask, 'mode', 'Destructive actions always need approval in Smart mode.'),
                'risk' => ActionRisk::High,
                'review' => null,
            ];
        }

        $review = $this->reviewer->review($context, $call);

        return [
            ...$this->verdict($review['risk'] === ActionRisk::Low ? ActionVerdict::Allow : ActionVerdict::Ask, 'reviewer', $review['reason']),
            'risk' => $review['risk'],
            'review' => ['reason' => $review['reason'], 'usage' => $review['usage']],
        ];
    }

    /**
     * @return array{verdict: ActionVerdict, reason: array<string, mixed>}|null
     */
    private function rateLimitVerdict(ActionContext $context, ToolCall $call): ?array
    {
        $limit = ($call->policy ?? [])['rate_limit'] ?? null;

        if (! is_array($limit) || ! isset($limit['max'])) {
            return null;
        }

        $per = match ($limit['per'] ?? 'hour') {
            'minute' => now()->subMinute(),
            'day' => now()->subDay(),
            default => now()->subHour(),
        };

        $count = AgentAction::query()
            ->where('agent_id', $context->agent->id)
            ->where('tool_name', $call->toolName)
            ->whereIn('status', [AgentActionStatus::Running, AgentActionStatus::Executed, AgentActionStatus::Failed])
            ->where('created_at', '>=', $per)
            ->count();

        if ($count < (int) $limit['max']) {
            return null;
        }

        $then = ActionVerdict::tryFrom((string) ($limit['then'] ?? 'ask')) ?? ActionVerdict::Ask;

        return $this->verdict($then, 'rate_limit', "This tool already ran {$count} times in the last ".($limit['per'] ?? 'hour').', the most allowed without a check.');
    }

    /**
     * A model that can't get a tool's arguments right tends to retry it
     * forever, and in Ask mode every retry is another approval for a
     * person. After a few failures in one turn the tool is refused, so the
     * model stops and explains instead.
     *
     * @return array{verdict: ActionVerdict, reason: array<string, mixed>}|null
     */
    private function repeatedFailureVerdict(ActionContext $context, ToolCall $call): ?array
    {
        $failures = AgentAction::query()
            ->where('run_id', $context->run->id)
            ->where('tool_name', $call->toolName)
            ->where('status', AgentActionStatus::Failed)
            ->count();

        if ($failures < self::MAX_FAILURES_PER_TURN) {
            return null;
        }

        return $this->verdict(ActionVerdict::Deny, 'repeated_failures', "This tool already failed {$failures} times in this turn. Stop calling it and tell the user what went wrong.");
    }

    /**
     * Every workspace guardrail that applies to this call. A guardrail
     * scoped by neither `tools` nor `effects` applies to every action that
     * changes something, never to reads, unless it names them.
     *
     * @return list<array{verdict: ActionVerdict, reason: array<string, mixed>}>
     */
    private function guardrailVerdicts(ActionContext $context, ToolCall $call): array
    {
        $verdicts = [];

        foreach ($context->policy->guardrails ?? [] as $guardrail) {
            $then = ActionVerdict::tryFrom((string) ($guardrail['then'] ?? ''));

            if ($then === null || ! $this->guardrailApplies($guardrail, $call)) {
                continue;
            }

            $verdicts[] = $this->verdict($then, 'guardrail', $guardrail['name'] ?? 'A workspace guardrail applies to this action.');
        }

        return $verdicts;
    }

    /**
     * @param  array<string, mixed>  $guardrail
     */
    private function guardrailApplies(array $guardrail, ToolCall $call): bool
    {
        $tools = $guardrail['tools'] ?? [];
        $effects = $guardrail['effects'] ?? [];

        if ($tools !== [] && ! collect($tools)->contains(fn (string $pattern): bool => Str::is($pattern, $call->toolName))) {
            return false;
        }

        if ($effects !== [] && ! in_array($call->effect->value, $effects, true)) {
            return false;
        }

        if ($tools === [] && $effects === [] && $call->effect->isRead()) {
            return false;
        }

        return collect($guardrail['conditions'] ?? [])->every(fn (array $condition): bool => $this->conditions->matches($condition, $call->effectiveArguments));
    }

    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>|null  $candidate
     * @return array<string, mixed>
     */
    private function tighten(array $decision, ?array $candidate): array
    {
        if ($candidate === null || $candidate['verdict']->rank() <= $decision['verdict']->rank()) {
            return $decision;
        }

        return [...$decision, ...$candidate];
    }

    /**
     * @return array{verdict: ActionVerdict, reason: array{source: string, detail: string}}
     */
    private function verdict(ActionVerdict $verdict, string $source, string $detail): array
    {
        return ['verdict' => $verdict, 'reason' => ['source' => $source, 'detail' => $detail]];
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function describeCondition(array $condition): string
    {
        $value = is_array($condition['value'] ?? null) ? implode(', ', $condition['value']) : ($condition['value'] ?? '');

        return trim("Rule matched: {$condition['field']} ".str_replace('_', ' ', (string) ($condition['op'] ?? 'eq'))." {$value}");
    }

    private function existing(ActionContext $context, string $toolCallId): ?AgentAction
    {
        return AgentAction::query()
            ->where('run_id', $context->run->id)
            ->where('agent_session_id', $context->session?->id)
            ->where('tool_call_id', $toolCallId)
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function record(ActionContext $context, ToolCall $call, array $decision): AgentAction
    {
        $verdict = $decision['verdict'];
        $asks = $verdict === ActionVerdict::Ask;

        $action = new AgentAction([
            'workspace_id' => $context->agent->workspace_id,
            'agent_id' => $context->agent->id,
            'agent_session_id' => $context->session?->id,
            'run_id' => $context->run->id,
            'plan_id' => $decision['plan_id'] ?? null,
            'tool_call_id' => $call->toolCallId,
            'tool_name' => $call->toolName,
            'tool_kind' => $call->kind,
            'effect' => $call->effect,
            'arguments' => $call->arguments,
            'outcome' => $verdict,
            'reason' => $decision['reason'],
            'risk' => $decision['risk'] ?? null,
            'review' => $decision['review'] ?? null,
            'approvers' => $asks ? (($call->policy ?? [])['approvers'] ?? null) : null,
            'requested_at' => $asks ? now() : null,
            'expires_at' => $asks ? now()->addMinutes($context->policy->approval_ttl_minutes) : null,
        ]);

        // `status` is engine-managed, so not fillable: set before the first save.
        $action->forceFill(['status' => match ($verdict) {
            ActionVerdict::Allow => AgentActionStatus::Running,
            ActionVerdict::Ask => AgentActionStatus::Pending,
            ActionVerdict::Deny => AgentActionStatus::Denied,
            ActionVerdict::Simulate => AgentActionStatus::Simulated,
        }])->save();

        return $action;
    }
}
