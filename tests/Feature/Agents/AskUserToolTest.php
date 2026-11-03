<?php

use App\Actions\Agents\ResolveAgentActionsAction;
use App\Ai\Agents\Workspace\WorkspaceAgent;
use App\Ai\Tools\Workspace\AskUserTool;
use App\Enums\Agents\ActionToolKind;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\Agents\AutonomyMode;
use App\Enums\RunStatus;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Runs\Run;
use App\Models\User;
use App\Notifications\Agents\AgentActionApprovalRequestedNotification;
use App\Services\Agents\AgentRunner;
use App\Services\Agents\ToolRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\ToolNameResolver;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create(['autonomy_mode' => AutonomyMode::Autopilot, 'allow_self_clone' => false]);
    $this->session = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);

    $this->ask = fn (array $question, string $after = 'Thanks, done.') => (function () use ($question, $after) {
        WorkspaceAgent::fake([new ToolCall('ask-1', AskUserTool::NAME, $question), $after]);

        return app(AgentRunner::class)->run($this->session, 'Book me a meeting room.');
    })();

    $this->answer = fn (mixed $answer, ?User $as = null) => app(ResolveAgentActionsAction::class)->execute($as ?? $this->owner, [[
        'action_id' => AgentAction::query()->sole()->id,
        'decision' => 'answer',
        'answer' => $answer,
    ]]);
});

it('pauses the turn on a question and records it as a question, not an approval', function () {
    Notification::fake();

    $reply = ($this->ask)(['question' => 'Which office?', 'options' => ['Berlin', 'Lisbon']]);

    $action = AgentAction::query()->sole();
    expect($action->tool_kind)->toBe(ActionToolKind::Question)
        ->and($action->status)->toBe(AgentActionStatus::Pending)
        ->and($action->agent_message_id)->toBe($reply->id)
        ->and($action->arguments)->toMatchArray(['question' => 'Which office?', 'options' => ['Berlin', 'Lisbon'], 'allow_other' => true]);

    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->status)->toBe(RunStatus::AwaitingApproval);
    expect($reply->paused_state['pending_tool_call_ids'])->toBe(['ask-1']);

    // Nobody is asked to "approve" a question: it's answered in the chat.
    Notification::assertNothingSentTo($this->owner, AgentActionApprovalRequestedNotification::class);
});

it('hands the answer back to the agent and finishes the same turn', function () {
    $paused = ($this->ask)(['question' => 'Which office?', 'options' => ['Berlin', 'Lisbon']], 'Booked a room in Lisbon.');

    ($this->answer)('Lisbon');

    $action = AgentAction::query()->sole();
    expect($action->status)->toBe(AgentActionStatus::Executed)
        ->and($action->decided_by)->toBe($this->owner->id);

    $reply = $paused->fresh();
    $result = json_decode(collect($reply->tool_results)->firstWhere('id', 'ask-1')['result'], true);

    expect($result['status'])->toBe('answered')
        ->and($result['answer'])->toBe('Lisbon');
    expect($reply->content)->toContain('Booked a room in Lisbon.');
    expect($reply->paused_state)->toBeNull();
    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->status)->toBe(RunStatus::Completed);
});

it('accepts several picked options when the question allows it', function () {
    ($this->ask)(['question' => 'Which days?', 'options' => ['Mon', 'Tue', 'Wed'], 'multiple' => true, 'allow_other' => false]);

    ($this->answer)(['Mon', 'Wed']);

    expect(json_decode(AgentAction::query()->sole()->result, true)['answer'])->toBe(['Mon', 'Wed']);
});

it('refuses an answer the question does not allow', function (array $question, mixed $answer) {
    ($this->ask)($question);

    expect(fn () => ($this->answer)($answer))->toThrow(ValidationException::class);
    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Pending);
})->with([
    'an option that was not offered' => [['question' => 'Which office?', 'options' => ['Berlin', 'Lisbon'], 'allow_other' => false], 'Paris'],
    'an empty answer' => [['question' => 'What is the topic?'], '   '],
    'a form missing a required field' => [['question' => 'Details?', 'fields' => [
        ['name' => 'title', 'label' => 'Title', 'required' => true],
        ['name' => 'attendees', 'label' => 'Attendees', 'type' => 'number'],
    ]], ['attendees' => '4']],
    'a form with a number that is not one' => [['question' => 'Details?', 'fields' => [
        ['name' => 'attendees', 'label' => 'Attendees', 'type' => 'number'],
    ]], ['attendees' => 'a few']],
]);

it('takes a form answer keyed by field name', function () {
    ($this->ask)(['question' => 'Meeting details?', 'fields' => [
        ['name' => 'title', 'label' => 'Title', 'required' => true],
        ['name' => 'room', 'label' => 'Room', 'type' => 'select', 'options' => ['Small', 'Large']],
        ['name' => 'video', 'label' => 'Video call', 'type' => 'checkbox'],
    ]]);

    ($this->answer)(['title' => 'Kickoff', 'room' => 'Large', 'video' => true, 'ignored' => 'x']);

    expect(json_decode(AgentAction::query()->sole()->result, true)['answer'])->toBe(['title' => 'Kickoff', 'room' => 'Large', 'video' => true]);
});

it('leaves a question waiting when it is "approved" rather than answered', function () {
    ($this->ask)(['question' => 'Which office?']);

    $decided = app(ResolveAgentActionsAction::class)->execute($this->owner, [['action_id' => AgentAction::query()->sole()->id, 'decision' => 'approve']]);

    expect($decided)->toBeEmpty();
    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Pending);
});

it('tells the agent when the question is skipped', function () {
    $paused = ($this->ask)(['question' => 'Which office?'], 'Fine, I picked Berlin.');

    app(ResolveAgentActionsAction::class)->execute($this->owner, [['action_id' => AgentAction::query()->sole()->id, 'decision' => 'reject', 'note' => 'You pick.']]);

    $result = collect($paused->fresh()->tool_results)->firstWhere('id', 'ask-1')['result'];

    expect($result)->toContain('chose not to answer')->toContain('You pick.');
    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->status)->toBe(RunStatus::Completed);
});

it('keeps the question in the history when the person replies by writing a new message', function () {
    $paused = ($this->ask)(['question' => 'Which office?'], 'Booked Lisbon.');

    WorkspaceAgent::fake(['Booked Lisbon.']);
    app(AgentRunner::class)->run($this->session, 'Lisbon please.');

    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Cancelled);

    // The cancelled call keeps an outcome, so the next turn still sees what
    // was asked and is told to read the new message as the answer.
    $result = collect($paused->fresh()->tool_results)->firstWhere('id', 'ask-1')['result'];
    expect($result)->toContain('wrote a new message instead');

    $replayed = collect((new WorkspaceAgent('', $this->session->fresh()))->messages())
        ->flatMap(fn ($message) => $message->toolResults ?? [])
        ->map(fn ($result) => $result->id);

    expect($replayed)->toContain('ask-1');
});

it('only lets the person in the conversation answer, not another approver', function () {
    ($this->ask)(['question' => 'Which office?']);

    $approver = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $approver->id, 'role' => Role::Member, 'joined_at' => now()]);

    expect(fn () => ($this->answer)('Lisbon', $approver))->toThrow(AuthorizationException::class);
});

it('answers through the chat endpoint and keeps questions out of the approvals inbox', function () {
    ($this->ask)(['question' => 'Which office?', 'options' => ['Berlin', 'Lisbon']], 'Lisbon it is.');

    Passport::actingAs($this->owner);

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/agent-actions")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/sessions/{$this->session->id}/actions/decisions", [
        'decisions' => [['action_id' => AgentAction::query()->sole()->id, 'decision' => 'answer', 'answer' => 'Lisbon']],
    ])->assertOk()->assertJsonPath('data.resumed', true);

    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Executed);
});

it('requires an answer for an answer decision', function () {
    ($this->ask)(['question' => 'Which office?']);

    Passport::actingAs($this->owner);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/sessions/{$this->session->id}/actions/decisions", [
        'decisions' => [['action_id' => AgentAction::query()->sole()->id, 'decision' => 'answer']],
    ])->assertUnprocessable()->assertJsonValidationErrors('decisions.0.answer');
});

it('is only offered in a chat turn of a top-level conversation', function () {
    $toolNames = fn (Run $run) => collect(app(ToolRegistry::class)->toolsFor($this->agent, $run))->map(fn ($tool) => ToolNameResolver::resolve($tool));

    $chat = $this->session->runs()->create(['workspace_id' => $this->workspace->id, 'trigger_type' => 'manual']);
    $scheduled = $this->session->runs()->create(['workspace_id' => $this->workspace->id, 'trigger_type' => 'schedule']);

    $child = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $child->forceFill(['parent_session_id' => $this->session->id])->save();
    $subagent = $child->runs()->create(['workspace_id' => $this->workspace->id, 'trigger_type' => 'manual']);

    expect($toolNames($chat))->toContain(AskUserTool::NAME);
    expect($toolNames($scheduled))->not->toContain(AskUserTool::NAME);
    expect($toolNames($subagent))->not->toContain(AskUserTool::NAME);
    expect(collect(app(ToolRegistry::class)->toolsFor($this->agent, $chat, canPause: false))->map(fn ($tool) => ToolNameResolver::resolve($tool)))->not->toContain(AskUserTool::NAME);
});
