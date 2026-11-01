<?php

use App\Ai\Agents\ActionReviewerAgent;
use App\Ai\Tools\SubmitRiskAssessmentTool;
use App\Enums\Agents\ActionEffect;
use App\Enums\Agents\ActionRisk;
use App\Enums\Agents\ActionToolKind;
use App\Enums\Agents\ActionVerdict;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\Agents\AgentPlanStatus;
use App\Enums\Agents\AutonomyMode;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentPlan;
use App\Models\Agents\WorkspaceAgentPolicy;
use App\Models\User;
use App\Services\Agents\Approvals\ActionContext;
use App\Services\Agents\Approvals\ActionGate;
use App\Services\Agents\Approvals\AutonomyResolver;
use App\Services\Agents\Approvals\ToolCall;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Responses\Data\ToolCall as ModelToolCall;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create(['autonomy_mode' => AutonomyMode::Ask]);
    $this->session = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->run = $this->session->runs()->create(['workspace_id' => $this->workspace->id, 'trigger_type' => 'manual']);

    $this->context = fn (bool $canPause = true): ActionContext => app(AutonomyResolver::class)
        ->contextFor($this->agent->fresh(), $this->run, $this->session->fresh(), $canPause);

    $this->call = fn (ActionEffect $effect = ActionEffect::External, array $arguments = ['to' => 'ana@acme.com'], ?array $policy = null, ?string $id = 'call-1'): ToolCall => new ToolCall(
        toolName: 'gmail_send_email',
        kind: ActionToolKind::Node,
        effect: $effect,
        arguments: $arguments,
        effectiveArguments: $arguments,
        toolCallId: $id,
        policy: $policy,
    );

    $this->weigh = fn (ToolCall $call, bool $canPause = true) => app(ActionGate::class)->weigh(($this->context)($canPause), $call);
});

it('asks before a write in Ask mode and records a pending action that expires', function () {
    $decision = ($this->weigh)(($this->call)());

    expect($decision->verdict)->toBe(ActionVerdict::Ask);
    expect($decision->action->status)->toBe(AgentActionStatus::Pending);
    expect($decision->action->expires_at)->not->toBeNull();
    expect($decision->action->run_id)->toBe($this->run->id);
});

it('lets a read run without asking and does not log it', function () {
    $decision = ($this->weigh)(($this->call)(ActionEffect::Read));

    expect($decision->verdict)->toBe(ActionVerdict::Allow);
    expect($decision->action)->toBeNull();
    expect(AgentAction::count())->toBe(0);
});

it('refuses every write in Read-only mode, even when the tool is set to always run', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::ReadOnly]);

    $decision = ($this->weigh)(($this->call)(policy: ['mode' => 'allow']));

    expect($decision->verdict)->toBe(ActionVerdict::Deny);
    expect($decision->action->status)->toBe(AgentActionStatus::Denied);
});

it('runs a tool set to always allow without asking, even in Ask mode', function () {
    $decision = ($this->weigh)(($this->call)(policy: ['mode' => 'allow']));

    expect($decision->verdict)->toBe(ActionVerdict::Allow);
    expect($decision->action->status)->toBe(AgentActionStatus::Running);
});

it('applies the first matching rule condition over the mode', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Autopilot]);

    $policy = ['conditions' => [['field' => 'to', 'op' => 'domain_not_in', 'value' => ['acme.com'], 'then' => 'ask']]];

    expect(($this->weigh)(($this->call)(arguments: ['to' => 'ana@acme.com'], policy: $policy, id: 'call-in'))->verdict)->toBe(ActionVerdict::Allow);
    expect(($this->weigh)(($this->call)(arguments: ['to' => 'ana@acme.com, eve@rival.com'], policy: $policy, id: 'call-out'))->verdict)->toBe(ActionVerdict::Ask);
});

it('never lets an agent setting get past a workspace guardrail', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Autopilot]);
    WorkspaceAgentPolicy::query()->create([
        'workspace_id' => $this->workspace->id,
        'guardrails' => [['name' => 'No email leaves the company', 'tools' => ['gmail_*'], 'then' => 'deny']],
    ]);

    $decision = ($this->weigh)(($this->call)(policy: ['mode' => 'allow']));

    expect($decision->verdict)->toBe(ActionVerdict::Deny);
    expect($decision->reason['source'])->toBe('guardrail');
    expect($decision->reason['detail'])->toBe('No email leaves the company');
});

it('caps the mode at the workspace maximum', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Autopilot]);
    WorkspaceAgentPolicy::query()->create(['workspace_id' => $this->workspace->id, 'max_autonomy_mode' => AutonomyMode::Ask]);

    expect(($this->weigh)(($this->call)())->verdict)->toBe(ActionVerdict::Ask);
});

it('still asks before a destructive action on Autopilot unless the workspace allows it', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Autopilot]);

    expect(($this->weigh)(($this->call)(ActionEffect::Destructive, id: 'call-a'))->verdict)->toBe(ActionVerdict::Ask);
    expect(($this->weigh)(($this->call)(ActionEffect::External, id: 'call-b'))->verdict)->toBe(ActionVerdict::Allow);

    WorkspaceAgentPolicy::query()->create(['workspace_id' => $this->workspace->id, 'allow_destructive_in_autopilot' => true]);

    expect(($this->weigh)(($this->call)(ActionEffect::Destructive, id: 'call-c'))->verdict)->toBe(ActionVerdict::Allow);
});

it('simulates instead of running or asking in Test run', function () {
    $this->agent->update(['test_mode' => true]);

    $decision = ($this->weigh)(($this->call)());

    expect($decision->verdict)->toBe(ActionVerdict::Simulate);
    expect($decision->action->status)->toBe(AgentActionStatus::Simulated);
});

it('simulates a call that would ask when there is no conversation to pause', function () {
    expect(($this->weigh)(($this->call)(), canPause: false)->verdict)->toBe(ActionVerdict::Simulate);
});

it('asks once a tool hits its rate limit', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Autopilot]);
    $policy = ['rate_limit' => ['max' => 2, 'per' => 'hour']];

    AgentAction::factory()->forSession($this->session)->count(2)->withStatus(AgentActionStatus::Executed)->create(['tool_name' => 'gmail_send_email']);

    $decision = ($this->weigh)(($this->call)(policy: $policy));

    expect($decision->verdict)->toBe(ActionVerdict::Ask);
    expect($decision->reason['source'])->toBe('rate_limit');
});

it('refuses a tool that keeps failing in the same turn instead of asking again', function () {
    AgentAction::factory()->forSession($this->session)->count(2)->withStatus(AgentActionStatus::Failed)->create(['tool_name' => 'gmail_send_email', 'run_id' => $this->run->id]);

    expect(($this->weigh)(($this->call)(id: 'call-a'))->verdict)->toBe(ActionVerdict::Ask);

    AgentAction::factory()->forSession($this->session)->withStatus(AgentActionStatus::Failed)->create(['tool_name' => 'gmail_send_email', 'run_id' => $this->run->id]);

    $decision = ($this->weigh)(($this->call)(id: 'call-b'));

    expect($decision->verdict)->toBe(ActionVerdict::Deny)
        ->and($decision->reason['source'])->toBe('repeated_failures')
        ->and($decision->action->status)->toBe(AgentActionStatus::Denied);
});

it('counts only failures from the current turn', function () {
    $earlier = $this->session->runs()->create(['workspace_id' => $this->workspace->id, 'trigger_type' => 'manual']);
    AgentAction::factory()->forSession($this->session)->count(3)->withStatus(AgentActionStatus::Failed)->create(['tool_name' => 'gmail_send_email', 'run_id' => $earlier->id]);

    expect(($this->weigh)(($this->call)())->verdict)->toBe(ActionVerdict::Ask);
});

it('returns the recorded decision when the same call is weighed again', function () {
    $first = ($this->weigh)(($this->call)());
    $this->agent->update(['autonomy_mode' => AutonomyMode::Autopilot]);
    $second = ($this->weigh)(($this->call)());

    expect($second->action->id)->toBe($first->action->id);
    expect($second->verdict)->toBe(ActionVerdict::Ask);
    expect(AgentAction::count())->toBe(1);
});

it('runs low-risk writes and asks about the rest in Smart mode', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Smart]);

    ActionReviewerAgent::fake([
        new ModelToolCall('r1', SubmitRiskAssessmentTool::NAME, ['risk' => 'low', 'reason' => 'The user asked for exactly this.']),
        new ModelToolCall('r2', SubmitRiskAssessmentTool::NAME, ['risk' => 'high', 'reason' => 'It emails someone the user never mentioned.']),
    ]);

    $low = ($this->weigh)(($this->call)(id: 'call-low'));
    $high = ($this->weigh)(($this->call)(id: 'call-high'));

    expect($low->verdict)->toBe(ActionVerdict::Allow);
    expect($low->risk)->toBe(ActionRisk::Low);
    expect($high->verdict)->toBe(ActionVerdict::Ask);
    expect($high->action->reason['detail'])->toBe('It emails someone the user never mentioned.');
});

it('asks when the Smart-mode reviewer fails', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Smart]);

    ActionReviewerAgent::fake(fn () => throw new RuntimeException('provider down'));

    $decision = ($this->weigh)(($this->call)());

    expect($decision->verdict)->toBe(ActionVerdict::Ask);
    expect($decision->risk)->toBe(ActionRisk::High);
});

it('blocks writes in Plan mode until a plan is approved, then runs only its steps', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Plan]);

    expect(($this->weigh)(($this->call)(id: 'call-before'))->verdict)->toBe(ActionVerdict::Deny);

    $plan = AgentPlan::factory()->forSession($this->session)->create([
        'steps' => [['id' => 'step-1', 'tool' => 'gmail_send_email', 'summary' => 'Email Ana', 'arguments' => ['to' => 'ana@acme.com'], 'status' => AgentPlan::STEP_PENDING]],
    ]);
    $plan->forceFill(['status' => AgentPlanStatus::Approved])->save();

    $inPlan = ($this->weigh)(($this->call)(arguments: ['to' => 'ANA@acme.com '], id: 'call-in-plan'));
    $offPlan = ($this->weigh)(($this->call)(arguments: ['to' => 'bob@acme.com'], id: 'call-off-plan'));

    expect($inPlan->verdict)->toBe(ActionVerdict::Allow);
    expect($inPlan->action->plan_id)->toBe($plan->id);
    expect($offPlan->verdict)->toBe(ActionVerdict::Ask);
});

it('reads the tighter mode from a conversation override', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Autopilot]);
    $this->session->update(['autonomy_mode' => AutonomyMode::ReadOnly]);

    expect(($this->weigh)(($this->call)())->verdict)->toBe(ActionVerdict::Deny);
});

it('does not let an always-allow tool rule get past the workspace maximum mode', function () {
    WorkspaceAgentPolicy::query()->create(['workspace_id' => $this->workspace->id, 'max_autonomy_mode' => AutonomyMode::Ask]);

    $decision = ($this->weigh)(($this->call)(policy: ['mode' => 'allow']));

    expect($decision->verdict)->toBe(ActionVerdict::Ask);
    expect($decision->action->status)->toBe(AgentActionStatus::Pending);
});

it('still asks before an always-allowed destructive tool under an Autopilot cap', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Autopilot]);
    WorkspaceAgentPolicy::query()->create(['workspace_id' => $this->workspace->id, 'max_autonomy_mode' => AutonomyMode::Autopilot]);

    expect(($this->weigh)(($this->call)(ActionEffect::Destructive, policy: ['mode' => 'allow'], id: 'call-delete'))->verdict)->toBe(ActionVerdict::Ask);
    expect(($this->weigh)(($this->call)(ActionEffect::External, policy: ['mode' => 'allow'], id: 'call-send'))->verdict)->toBe(ActionVerdict::Allow);
});

it('asks rather than refers to a plan when a Plan cap meets a conversation in a stricter mode', function () {
    WorkspaceAgentPolicy::query()->create(['workspace_id' => $this->workspace->id, 'max_autonomy_mode' => AutonomyMode::Plan]);

    expect(($this->weigh)(($this->call)(policy: ['mode' => 'allow']))->verdict)->toBe(ActionVerdict::Ask);
});

it('lets one approved plan step run only one call', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Plan]);

    $plan = AgentPlan::factory()->forSession($this->session)->create([
        'steps' => [['id' => 'step-1', 'tool' => 'gmail_send_email', 'summary' => 'Email Ana', 'arguments' => ['to' => 'ana@acme.com'], 'status' => AgentPlan::STEP_PENDING]],
    ]);
    $plan->forceFill(['status' => AgentPlanStatus::Approved])->save();

    $first = ($this->weigh)(($this->call)(id: 'call-first'));
    $second = ($this->weigh)(($this->call)(id: 'call-second'));

    expect($first->verdict)->toBe(ActionVerdict::Allow);
    expect($second->verdict)->toBe(ActionVerdict::Ask);
    expect($second->action->reason['source'])->toBe('plan');
});
