<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Ai\Tools\SubmitPlanTool;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\Agents\AgentPlanStatus;
use App\Enums\Agents\AutonomyMode;
use App\Enums\RunStatus;
use App\Enums\Workspaces\Role;
use App\Events\Agents\AgentActionsRequested;
use App\Events\Agents\AgentTurnChanged;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentPlan;
use App\Models\Agents\WorkspaceAgentPolicy;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Agents\AgentRunner;
use App\Services\Agents\Approvals\ActionApprovers;
use App\Services\Agents\Approvals\ChatApprovalLinks;
use App\Services\Http\SsrfGuard;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Passport\Passport;

beforeEach(function () {
    app()->instance(SsrfGuard::class, new SsrfGuard(fn () => ['203.0.113.10']));
    Http::fake(['hooks.acme.test/*' => Http::response(['ok' => true])]);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create(['autonomy_mode' => AutonomyMode::Ask]);
    $this->binding = $this->agent->toolBindings()->create([
        'node_type' => 'call_api',
        'config' => ['url' => 'https://hooks.acme.test/notify', 'method' => 'POST'],
        'exposed_fields' => ['body'],
    ]);
    $this->session = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);

    $this->base = "/api/v1/workspaces/{$this->workspace->id}";
    $this->sessionUrl = "{$this->base}/agents/{$this->agent->id}/sessions/{$this->session->id}";

    $this->pause = function (): void {
        WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Posted it.']);
        app(AgentRunner::class)->run($this->session, 'Post it.');
    };

    $this->addMember = function (Role $role): User {
        $user = User::factory()->create();
        $this->workspace->members()->create(['user_id' => $user->id, 'role' => $role, 'joined_at' => now()]);

        return $user;
    };

    Passport::actingAs($this->owner);
});

it('tells the chat a turn paused for approval', function () {
    Event::fake([AgentActionsRequested::class, AgentTurnChanged::class]);
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Posted it.']);

    $this->postJson("{$this->sessionUrl}/turns", ['message' => 'Post it.'])->assertAccepted();

    $action = AgentAction::query()->sole();

    Event::assertDispatched(AgentActionsRequested::class, fn (AgentActionsRequested $event): bool => $event->actions->modelKeys() === [$action->id]);
    Event::assertDispatched(AgentTurnChanged::class, function (AgentTurnChanged $event) use ($action): bool {
        $turn = $event->broadcastWith()['turn'];

        return $turn['status'] === 'awaiting_approval' && $turn['pending_action_ids'] === [$action->id];
    });
    Http::assertNothingSent();
});

it('continues the paused turn on the queue once the chat decides', function () {
    ($this->pause)();
    $action = AgentAction::query()->sole();

    Event::fake([AgentTurnChanged::class]);

    $this->postJson("{$this->sessionUrl}/actions/decisions", [
        'decisions' => [['action_id' => $action->id, 'decision' => 'approve']],
    ])->assertOk()
        ->assertJsonPath('data.actions.0.id', $action->id)
        ->assertJsonPath('data.resumed', true)
        ->assertJsonPath('data.run_id', Run::query()->where('runnable_id', $this->session->id)->sole()->id);

    Http::assertSentCount(1);
    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->status)->toBe(RunStatus::Completed);

    $statuses = Event::dispatched(AgentTurnChanged::class)->map(fn (array $args): string => $args[0]->broadcastWith()['turn']['status']);
    expect($statuses->all())->toBe(['running', 'completed']);

    // Nothing is waiting any more, so a repeated decision resumes nothing.
    $this->postJson("{$this->sessionUrl}/actions/decisions", [
        'decisions' => [['action_id' => $action->id, 'decision' => 'approve']],
    ])->assertOk()->assertJsonPath('data.resumed', false)->assertJsonPath('data.run_id', null);
});

it('lists a conversation\'s actions for its approval cards', function () {
    ($this->pause)();

    $this->getJson("{$this->sessionUrl}/actions")
        ->assertOk()
        ->assertJsonPath('data.actions.0.tool_name', 'call_api')
        ->assertJsonPath('data.actions.0.status', 'pending')
        ->assertJsonPath('data.actions.0.effect', 'external');
});

it('lists waiting actions across the workspace and decides them in bulk', function () {
    ($this->pause)();
    $action = AgentAction::query()->sole();

    $this->getJson("{$this->base}/agent-actions")
        ->assertOk()
        ->assertJsonPath('data.0.id', $action->id);

    $this->postJson("{$this->base}/agent-actions/decisions", [
        'decisions' => [['action_id' => $action->id, 'decision' => 'reject', 'note' => 'Not today.']],
    ])->assertOk()->assertJsonPath('data.actions.0.status', 'rejected');

    Http::assertNothingSent();
    expect($action->fresh()->decision_note)->toBe('Not today.');
});

it('refuses decisions on another workspace\'s actions', function () {
    $other = AgentAction::factory()->create();

    $this->postJson("{$this->base}/agent-actions/decisions", [
        'decisions' => [['action_id' => $other->id, 'decision' => 'approve']],
    ])->assertNotFound();

    expect($other->fresh()->status)->toBe(AgentActionStatus::Pending);
});

it('forbids a plain member from deciding someone else\'s action', function () {
    ($this->pause)();
    Passport::actingAs(($this->addMember)(Role::Member));

    $this->postJson("{$this->base}/agent-actions/decisions", [
        'decisions' => [['action_id' => AgentAction::query()->sole()->id, 'decision' => 'approve']],
    ])->assertForbidden();

    Http::assertNothingSent();
});

it('lets an editor decide, and remembers "always allow" only for someone who manages agents', function () {
    ($this->pause)();
    Passport::actingAs(($this->addMember)(Role::Editor));

    $this->postJson("{$this->base}/agent-actions/decisions", [
        'decisions' => [['action_id' => AgentAction::query()->sole()->id, 'decision' => 'approve', 'remember' => true]],
    ])->assertOk();

    expect($this->binding->fresh()->approval_policy['mode'])->toBe('allow');
});

it('shows the dashboard how many agent actions are waiting', function () {
    ($this->pause)();

    $this->getJson("{$this->base}/dashboard")->assertOk()->assertJsonPath('data.pending_agent_actions', 1);
});

it('saves a tool\'s approval rule and rejects a malformed one', function () {
    $url = "{$this->base}/agents/{$this->agent->id}/tool-bindings/{$this->binding->id}";

    $this->patchJson($url, ['approval_policy' => [
        'mode' => 'ask',
        'conditions' => [['field' => 'body.channel', 'op' => 'in', 'value' => ['#exec'], 'then' => 'deny']],
        'rate_limit' => ['max' => 10, 'per' => 'hour'],
        'approvers' => ['role:admin'],
    ]])->assertOk()->assertJsonPath('data.tool_binding.approval_policy.mode', 'ask');

    $this->patchJson($url, ['approval_policy' => ['conditions' => [['field' => 'x', 'op' => 'sounds_like', 'then' => 'ask']]]])
        ->assertUnprocessable();
});

it('saves an approval rule for a workflow attached as a tool', function () {
    $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
    $this->agent->workflows()->attach($workflow->id);

    $this->patchJson("{$this->base}/agents/{$this->agent->id}/workflows/{$workflow->id}", ['approval_policy' => ['mode' => 'deny']])
        ->assertOk()
        ->assertJsonPath('data.workflows.0.approval_policy.mode', 'deny');
});

it('sets an agent\'s mode and Test run', function () {
    $this->patchJson("{$this->base}/agents/{$this->agent->id}", ['autonomy_mode' => 'smart', 'test_mode' => true])
        ->assertOk()
        ->assertJsonPath('data.agent.autonomy_mode', 'smart')
        ->assertJsonPath('data.agent.test_mode', true);
});

it('lets anyone who chats tighten a conversation, but only an agent manager loosen it', function () {
    $member = ($this->addMember)(Role::Member);
    $session = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $member->id]);
    Passport::actingAs($member);

    $url = "{$this->base}/agents/{$this->agent->id}/sessions/{$session->id}";

    $this->patchJson($url, ['autonomy_mode' => 'read_only'])->assertOk()->assertJsonPath('data.session.autonomy_mode', 'read_only');
    $this->patchJson($url, ['autonomy_mode' => 'autopilot'])->assertForbidden();
});

it('lets only an admin change the workspace guardrails', function () {
    $this->putJson("{$this->base}/agent-policy", [
        'max_autonomy_mode' => 'smart',
        'guardrails' => [['name' => 'Never delete', 'effects' => ['destructive'], 'then' => 'deny']],
        'approval_ttl_minutes' => 60,
    ])->assertOk()->assertJsonPath('data.policy.max_autonomy_mode', 'smart');

    expect(WorkspaceAgentPolicy::forWorkspace($this->workspace->id)->guardrails[0]['then'])->toBe('deny');

    Passport::actingAs(($this->addMember)(Role::Editor));
    $this->putJson("{$this->base}/agent-policy", ['max_autonomy_mode' => 'autopilot'])->assertForbidden();
    $this->getJson("{$this->base}/agent-policy")->assertOk();
});

it('suggests trusting a tool people keep approving unchanged, and applies it', function () {
    AgentAction::factory()->forSession($this->session)->count(5)->withStatus(AgentActionStatus::Executed)->create(['tool_name' => 'call_api']);

    $url = "{$this->base}/agents/{$this->agent->id}/trust-suggestions";

    $this->getJson($url)->assertOk()->assertJsonPath('data.suggestions.0.tool_name', 'call_api');

    $this->postJson("{$url}/apply", ['tool_name' => 'call_api'])->assertOk()->assertJsonCount(0, 'data.suggestions');
    expect($this->binding->fresh()->approval_policy['mode'])->toBe('allow');
});

it('proposes a plan in Plan mode and runs its steps once approved', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Plan]);

    WorkspaceAgent::fake([
        new ToolCall('plan-1', SubmitPlanTool::NAME, [
            'title' => 'Notify the team',
            'steps' => [['tool' => 'call_api', 'summary' => 'Post the update', 'arguments' => ['body.text' => 'Deploy done']]],
        ]),
        'Here is my plan; approve it and I will go ahead.',
        new ToolCall('call-1', 'call_api', ['body' => ['text' => 'Deploy done']]),
        'Posted.',
    ]);

    app(AgentRunner::class)->run($this->session, 'Tell the team the deploy is done.');

    $plan = AgentPlan::query()->sole();
    expect($plan->status)->toBe(AgentPlanStatus::Proposed);
    Http::assertNothingSent();

    $this->postJson("{$this->sessionUrl}/plans/{$plan->id}/approve", ['execute' => true])->assertOk();

    Http::assertSentCount(1);
    expect($plan->fresh()->status)->toBe(AgentPlanStatus::Completed);
    expect(AgentAction::query()->where('tool_call_id', 'call-1')->sole()->status)->toBe(AgentActionStatus::Executed);
});

it('decides from a signed email link, but only when the form is submitted', function () {
    ($this->pause)();
    $action = AgentAction::query()->sole();

    $url = URL::temporarySignedRoute('agent-actions.signed-decision.show', now()->addHour(), ['action' => $action->id, 'user' => $this->owner->id]);

    $this->get($url)->assertOk()->assertSee('Approve');
    expect($action->fresh()->status)->toBe(AgentActionStatus::Pending);

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post($url, ['decision' => 'approve'])
        ->assertOk()
        ->assertSee('executed');

    Http::assertSentCount(1);
    $this->get(route('agent-actions.signed-decision.show', ['action' => $action->id, 'user' => $this->owner->id]))->assertForbidden();
});

it('decides from a Slack button when the workspace allows it and the request is signed', function () {
    ($this->pause)();
    $action = AgentAction::query()->sole();

    WorkspaceAgentPolicy::query()->create(['workspace_id' => $this->workspace->id, 'allow_chat_approvals' => true]);
    $this->workspace->notificationChannels()->create([
        'created_by' => $this->owner->id, 'type' => 'slack', 'name' => 'Approvals', 'config' => ['url' => 'https://hooks.slack.test/x', 'signing_secret' => 'shh'], 'is_active' => true,
    ]);

    $value = app(ChatApprovalLinks::class)->slackBlocks('t', collect([$action]))[2]['elements'][0]['value'];
    $payload = json_encode(['user' => ['username' => 'ana'], 'actions' => [['value' => $value]]]);
    $body = 'payload='.urlencode($payload);
    $timestamp = (string) time();

    $send = fn (string $secret) => $this->call('POST', '/api/slack/agent-actions', ['payload' => $payload], [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
        'HTTP_X_SLACK_SIGNATURE' => 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", $secret),
    ], $body);

    $send('wrong')->assertUnauthorized();
    expect($action->fresh()->status)->toBe(AgentActionStatus::Pending);

    $send('shh')->assertOk();
    expect($action->fresh()->decision_channel)->toBe('slack');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'hooks.acme.test'));
});

it('refuses deciding another conversation\'s actions from a chat', function () {
    $other = AgentAction::factory()->create();

    $this->postJson("{$this->sessionUrl}/actions/decisions", [
        'decisions' => [['action_id' => $other->id, 'decision' => 'approve']],
    ])->assertNotFound();

    expect($other->fresh()->status)->toBe(AgentActionStatus::Pending);
});

it('leaves an action with named approvers to them, not to a Slack button', function () {
    ($this->pause)();
    $action = AgentAction::query()->sole();
    $action->update(['approvers' => ["user:{$this->owner->id}"]]);

    WorkspaceAgentPolicy::query()->create(['workspace_id' => $this->workspace->id, 'allow_chat_approvals' => true]);
    $this->workspace->notificationChannels()->create([
        'created_by' => $this->owner->id, 'type' => 'slack', 'name' => 'Approvals', 'config' => ['url' => 'https://hooks.slack.test/x', 'signing_secret' => 'shh'], 'is_active' => true,
    ]);

    $blocks = app(ChatApprovalLinks::class)->slackBlocks('t', collect([$action]));
    expect(collect($blocks)->where('type', 'actions'))->toBeEmpty();

    // A button minted before the rule named its approvers.
    $value = Crypt::encryptString(json_encode(['action_id' => $action->id, 'decision' => ChatApprovalLinks::APPROVE]));
    $payload = json_encode(['user' => ['username' => 'eve'], 'actions' => [['value' => $value]]]);
    $body = 'payload='.urlencode($payload);
    $timestamp = (string) time();

    $this->call('POST', '/api/slack/agent-actions', ['payload' => $payload], [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
        'HTTP_X_SLACK_SIGNATURE' => 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", 'shh'),
    ], $body)->assertOk()->assertJsonPath('text', 'Only the approvers named for this tool can decide it, in the app.');

    expect($action->fresh()->status)->toBe(AgentActionStatus::Pending);
    Http::assertNothingSent();
});

it('tells each person only about the actions they may decide in a mixed batch', function () {
    ($this->pause)();
    $named = AgentAction::query()->sole();
    $named->update(['approvers' => ['role:member']]);

    $unnamed = $named->replicate();
    $unnamed->forceFill(['tool_call_id' => 'call-2', 'approvers' => null])->save();

    $member = ($this->addMember)(Role::Member);

    $entries = app(ActionApprovers::class)
        ->notificationsFor($this->workspace, AgentAction::query()->with('session')->get())
        ->keyBy(fn (array $entry): string => $entry['user']->id);

    expect($entries[$member->id]['actions']->modelKeys())->toBe([$named->id])
        ->and($entries[$this->owner->id]['actions']->modelKeys())->toEqualCanonicalizing([$named->id, $unnamed->id]);
});

it('keeps the signed decision page from someone who may not decide the action', function () {
    ($this->pause)();
    $action = AgentAction::query()->sole();
    $viewer = ($this->addMember)(Role::Viewer);

    $url = URL::temporarySignedRoute('agent-actions.signed-decision.show', now()->addHour(), ['action' => $action->id, 'user' => $viewer->id]);

    $this->get($url)->assertForbidden();
});
