<?php

use App\Actions\Agents\ResolveAgentActionsAction;
use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\Agents\AutonomyMode;
use App\Enums\RunStatus;
use App\Enums\Workspaces\Role;
use App\Events\Agents\AgentActionsRequested;
use App\Jobs\System\FailStuckAgentTurnsJob;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Billing\CreditTransaction;
use App\Models\Runs\Run;
use App\Models\User;
use App\Notifications\Agents\AgentActionApprovalRequestedNotification;
use App\Services\Agents\AgentRunner;
use App\Services\Http\SsrfGuard;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    app()->instance(SsrfGuard::class, new SsrfGuard(fn () => ['203.0.113.10']));
    Http::fake(['hooks.acme.test/*' => Http::response(['ok' => true])]);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create(['autonomy_mode' => AutonomyMode::Ask]);
    $this->agent->toolBindings()->create([
        'node_type' => 'call_api',
        'config' => ['url' => 'https://hooks.acme.test/notify', 'method' => 'POST'],
        'exposed_fields' => ['body'],
    ]);
    $this->session = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);

    $this->decide = fn (array $decision) => app(ResolveAgentActionsAction::class)->execute($this->owner, [[
        'action_id' => AgentAction::query()->sole()->id,
        ...$decision,
    ]]);
});

it('pauses the turn on an action that needs approval instead of running it', function () {
    Notification::fake();
    Event::fake([AgentActionsRequested::class]);
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'Deploy done']]), 'Posted it.']);

    $reply = app(AgentRunner::class)->run($this->session, 'Tell the team the deploy is done.');

    Http::assertNothingSent();

    $run = Run::query()->where('runnable_id', $this->session->id)->sole();
    expect($run->status)->toBe(RunStatus::AwaitingApproval);
    expect($reply->paused_state['pending_tool_call_ids'])->toBe(['call-1']);

    $action = AgentAction::query()->sole();
    expect($action->status)->toBe(AgentActionStatus::Pending);
    expect($action->agent_message_id)->toBe($reply->id);
    expect($run->output['pending_action_ids'])->toBe([$action->id]);

    Event::assertDispatched(AgentActionsRequested::class);
    expect(CreditTransaction::where('source_id', $reply->id)->exists())->toBeFalse();
});

it('notifies the conversation owner that an action is waiting', function () {
    Notification::fake();
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Posted.']);

    app(AgentRunner::class)->run($this->session, 'Post it.');

    Notification::assertSentTo($this->owner, AgentActionApprovalRequestedNotification::class);
});

it('runs an approved action exactly once, then finishes the same turn', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'Deploy done']]), 'Posted it for you.']);

    $paused = app(AgentRunner::class)->run($this->session, 'Tell the team the deploy is done.');

    ($this->decide)(['decision' => 'approve']);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.acme.test/notify' && $request['text'] === 'Deploy done');

    $action = AgentAction::query()->sole();
    expect($action->status)->toBe(AgentActionStatus::Executed);
    expect($action->decided_by)->toBe($this->owner->id);

    $reply = $paused->fresh();
    expect($reply->paused_state)->toBeNull();
    expect($reply->content)->toContain('Posted it for you.');
    expect(collect($reply->tool_results)->firstWhere('id', 'call-1')['result'])->toContain('"ok":true');

    $run = Run::query()->where('runnable_id', $this->session->id)->sole();
    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->output['message_id'])->toBe($reply->id);
    expect($this->session->messages()->where('role', AgentMessageRole::Assistant)->count())->toBe(1);
    expect(CreditTransaction::where('source_id', $reply->id)->exists())->toBeTrue();
});

it('never lets an edited approval override a value bound at attach time', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'draft']]), 'Done.']);

    app(AgentRunner::class)->run($this->session, 'Post it.');

    ($this->decide)(['decision' => 'edit', 'arguments' => [
        'url' => 'https://evil.example.com/exfiltrate',
        'method' => 'DELETE',
        'body' => ['text' => 'edited'],
    ]]);

    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.acme.test/notify'
        && $request->method() === 'POST'
        && $request['text'] === 'edited');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example.com'));
});

it('passes a rejection note back to the agent and never runs the action', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Understood, I will draft it instead.']);

    $paused = app(AgentRunner::class)->run($this->session, 'Post it.');

    ($this->decide)(['decision' => 'reject', 'note' => 'Draft it for me instead.']);

    Http::assertNothingSent();

    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Rejected);

    $result = collect($paused->fresh()->tool_results)->firstWhere('id', 'call-1')['result'];
    expect($result)->toContain('The user rejected this action')->toContain('Draft it for me instead.');
    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->status)->toBe(RunStatus::Completed);
});

it('waits for every action in the turn before resuming', function () {
    WorkspaceAgent::fake([
        new ToolCall('call-1', 'call_api', ['body' => ['n' => 1]]),
        'Both posted.',
    ]);

    app(AgentRunner::class)->run($this->session, 'Post twice.');

    $second = AgentAction::factory()->forSession($this->session)->create([
        'run_id' => AgentAction::query()->sole()->run_id,
        'agent_message_id' => AgentAction::query()->sole()->agent_message_id,
        'tool_call_id' => 'call-2',
        'tool_name' => 'call_api',
    ]);
    $message = $second->message;
    $message->forceFill(['paused_state' => [...$message->paused_state, 'pending_tool_call_ids' => ['call-1', 'call-2']]])->save();

    app(ResolveAgentActionsAction::class)->execute($this->owner, [['action_id' => AgentAction::query()->where('tool_call_id', 'call-1')->sole()->id, 'decision' => 'approve']]);

    Http::assertNothingSent();
    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->status)->toBe(RunStatus::AwaitingApproval);

    app(ResolveAgentActionsAction::class)->execute($this->owner, [['action_id' => $second->id, 'decision' => 'reject']]);

    Http::assertSentCount(1);
    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->status)->toBe(RunStatus::Completed);
});

it('ignores a second decision on an action that was already decided', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Done.']);

    app(AgentRunner::class)->run($this->session, 'Post it.');

    ($this->decide)(['decision' => 'approve']);
    $again = ($this->decide)(['decision' => 'reject']);

    expect($again)->toBeEmpty();
    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Executed);
    Http::assertSentCount(1);
});

it('cancels waiting actions and closes the paused turn when the person writes again', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Never mind then.']);

    app(AgentRunner::class)->run($this->session, 'Post it.');
    $pausedRun = Run::query()->where('runnable_id', $this->session->id)->sole();

    app(AgentRunner::class)->run($this->session, 'Actually, forget it.');

    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Cancelled);
    expect($pausedRun->fresh()->status)->toBe(RunStatus::Completed);
    Http::assertNothingSent();
});

it('expires an action nobody decided in time and lets the agent carry on', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Nobody approved, so I did not post.']);

    $paused = app(AgentRunner::class)->run($this->session, 'Post it.');

    $this->travel(2)->days();
    $this->artisan('agents:expire-actions')->assertSuccessful();

    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Expired);
    expect(collect($paused->fresh()->tool_results)->firstWhere('id', 'call-1')['result'])->toContain('Nobody approved this action in time');
    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->status)->toBe(RunStatus::Completed);
    Http::assertNothingSent();
});

it('does not fail a turn that is waiting on a person as stuck', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Done.']);

    app(AgentRunner::class)->run($this->session, 'Post it.');

    $this->travel(2)->hours();
    (new FailStuckAgentTurnsJob)->handle(app(AgentRunner::class));

    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->status)->toBe(RunStatus::AwaitingApproval);
});

it('runs actions without pausing on Autopilot', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Autopilot]);
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Posted.']);

    app(AgentRunner::class)->run($this->session, 'Post it.');

    Http::assertSentCount(1);
    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Executed);
    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->status)->toBe(RunStatus::Completed);
});

it('simulates actions in Test run and tells the agent so', function () {
    $this->agent->update(['test_mode' => true]);
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Simulated the post.']);

    $reply = app(AgentRunner::class)->run($this->session, 'Post it.');

    Http::assertNothingSent();
    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Simulated);
    expect(collect($reply->tool_results)->firstWhere('id', 'call-1')['result'])->toContain('"simulated":true');
});

it('refuses to decide for someone who may not approve', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Done.']);

    app(AgentRunner::class)->run($this->session, 'Post it.');

    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);

    expect(fn () => app(ResolveAgentActionsAction::class)->execute($member, [['action_id' => AgentAction::query()->sole()->id, 'decision' => 'approve']]))
        ->toThrow(AuthorizationException::class);

    Http::assertNothingSent();
});

it('does not expire an action someone decided while the expiry was running', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Posted it.']);

    app(AgentRunner::class)->run($this->session, 'Post it.');
    $this->travel(2)->days();

    // The decision lands between the expiry reading the action and writing it.
    AgentAction::retrieved(function (AgentAction $action): void {
        AgentAction::query()->whereKey($action->id)->where('status', AgentActionStatus::Pending)->update(['status' => AgentActionStatus::Approved]);
    });

    $this->artisan('agents:expire-actions')->expectsOutput('Expired 0 action(s).')->assertSuccessful();

    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Approved);
});

it('cancels an approved action that never ran when the person writes again', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['n' => 1]]), 'Never mind then.']);

    app(AgentRunner::class)->run($this->session, 'Post twice.');
    $first = AgentAction::query()->sole();

    $second = AgentAction::factory()->forSession($this->session)->create([
        'run_id' => $first->run_id,
        'agent_message_id' => $first->agent_message_id,
        'tool_call_id' => 'call-2',
        'tool_name' => 'call_api',
    ]);
    $message = $second->message;
    $message->forceFill(['paused_state' => [...$message->paused_state, 'pending_tool_call_ids' => ['call-1', 'call-2']]])->save();

    app(ResolveAgentActionsAction::class)->execute($this->owner, [['action_id' => $first->id, 'decision' => 'approve']]);
    app(AgentRunner::class)->run($this->session, 'Actually, forget it.');

    expect($first->fresh()->status)->toBe(AgentActionStatus::Cancelled);
    expect($second->fresh()->status)->toBe(AgentActionStatus::Cancelled);
    Http::assertNothingSent();
});

it('leaves a paused turn that is being resumed to finish when the person writes again', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Posted it.']);

    app(AgentRunner::class)->run($this->session, 'Post it.');
    $pausedRun = Run::query()->where('runnable_id', $this->session->id)->sole();

    // A resume claims the turn between the new message reading it and closing it.
    Run::retrieved(function (Run $run) use ($pausedRun): void {
        if ($run->id === $pausedRun->id) {
            Run::query()->whereKey($run->id)->where('status', RunStatus::AwaitingApproval)->update(['status' => RunStatus::Running]);
        }
    });

    app(AgentRunner::class)->abandonPausedTurns($this->session);

    expect($pausedRun->fresh()->status)->toBe(RunStatus::Running);
    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Pending);
});
