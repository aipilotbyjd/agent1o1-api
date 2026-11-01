<?php

use App\Actions\Agents\ResolveAgentActionsAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Agents\WorkspaceAgent;
use App\Ai\Tools\InvokeAgentTool;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\Agents\AutonomyMode;
use App\Enums\Agents\SubagentTaskStatus;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Enums\Triggers\TriggerEventStatus;
use App\Jobs\Agents\ResumeAgentTurnJob;
use App\Jobs\Agents\RunSubagentTaskJob;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentSession;
use App\Models\Agents\SubagentTask;
use App\Models\Auth\ApiKey;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Agents\AgentRunner;
use App\Services\Agents\AgentTurnBroadcaster;
use App\Services\Http\SsrfGuard;
use App\Services\Triggers\TriggerService;
use App\Services\Workflows\Engine\RunCanceller;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;

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

    $this->approveAll = fn () => app(ResolveAgentActionsAction::class)->execute(
        $this->owner,
        AgentAction::query()->where('status', AgentActionStatus::Pending)->pluck('id')->map(fn (string $id): array => ['action_id' => $id, 'decision' => 'approve'])->all(),
    );
});

it('parks a workflow\'s Agent node while its action waits, then finishes the run once approved', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'from the workflow']]), 'Posted from the workflow.']);

    $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'agent', 'type' => 'agent', 'config' => ['agent_id' => $this->agent->id, 'prompt' => 'Post it']]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $this->owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh());
    $nodeRun = $run->fresh(['nodeRuns'])->nodeRuns->sole();

    expect($nodeRun->status)->toBe(NodeRunStatus::AwaitingApproval);
    expect(AgentAction::query()->sole()->node_run_id)->toBe($nodeRun->id);
    Http::assertNothingSent();

    ($this->approveAll)();

    Http::assertSentCount(1);
    expect($nodeRun->fresh()->status)->toBe(NodeRunStatus::Completed);
    expect($nodeRun->fresh()->output['text'])->toContain('Posted from the workflow.');
    expect($run->fresh()->status)->toBe(RunStatus::Completed);
});

it('cancels a workflow\'s waiting agent actions when the run is cancelled', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'x']]), 'ok']);

    $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
    $workflow->replaceGraph(['nodes' => [['key' => 'agent', 'type' => 'agent', 'config' => ['agent_id' => $this->agent->id, 'prompt' => 'Post it']]], 'edges' => []]);
    $workflow->publishVersion(publisher: $this->owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh());
    app(RunCanceller::class)->cancel($run);

    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Cancelled);
});

it('runs a subagent no more freely than the conversation that started it', function () {
    Queue::fake();

    $helper = Agent::factory()->forWorkspace($this->workspace)->create(['name' => 'Helper', 'autonomy_mode' => AutonomyMode::Autopilot]);
    $parent = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);

    (new InvokeAgentTool($parent, ['Helper' => $helper], AutonomyMode::Ask, true))
        ->handle(new Request(['agent' => 'Helper', 'task' => 'Post the update']));

    $child = AgentSession::query()->where('parent_session_id', $parent->id)->sole();
    expect($child->autonomy_mode)->toBe(AutonomyMode::Ask);
    expect($child->test_mode)->toBeTrue();
});

it('holds a subagent\'s task while its action waits, and completes it once approved', function () {
    Queue::fake();

    $parent = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $child = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id, 'autonomy_mode' => AutonomyMode::Ask]);
    $child->forceFill(['parent_session_id' => $parent->id])->save();
    $task = SubagentTask::query()->create([
        'workspace_id' => $this->workspace->id,
        'parent_session_id' => $parent->id,
        'agent_id' => $this->agent->id,
        'session_id' => $child->id,
        'task' => 'Post the update',
    ]);

    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'x']]), 'Posted by the subagent.']);

    (new RunSubagentTaskJob($task->id))->handle(app(AgentRunner::class));

    expect($task->fresh()->status)->toBe(SubagentTaskStatus::AwaitingApproval);

    ($this->approveAll)();

    // The queue is faked, so run the resume the approval queued.
    Queue::assertPushed(ResumeAgentTurnJob::class);
    Queue::pushed(ResumeAgentTurnJob::class)->each(fn (ResumeAgentTurnJob $job) => $job->handle(app(AgentRunner::class), app(AgentTurnBroadcaster::class)));

    expect($task->fresh()->status)->toBe(SubagentTaskStatus::Completed);
    expect($task->fresh()->result)->toContain('Posted by the subagent.');
});

it('runs a triggered agent turn under the trigger\'s own mode', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Autopilot]);
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'x']]), 'Blocked, as expected.']);

    $trigger = Trigger::factory()->webhook()->forAgent($this->agent)->create([
        'created_by' => $this->owner->id,
        'config' => ['autonomy_mode' => 'read_only'],
    ]);
    $event = $trigger->events()->create([
        'source' => $trigger->type,
        'status' => TriggerEventStatus::Queued,
        'payload' => ['a' => 1],
        'delivery_id' => 'd-1',
    ]);

    (new FireTriggerEvent($trigger, $event))->handle(app(TriggerService::class));

    expect(AgentSession::query()->sole()->autonomy_mode)->toBe(AutonomyMode::ReadOnly);
    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Denied);
    Http::assertNothingSent();
});

it('lets an API caller see a reply on hold and decide its actions', function () {
    $session = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id]);
    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'x']]), 'Posted.']);

    $key = ApiKey::generatePlainTextKey();
    $this->workspace->apiKeys()->create(['name' => 'Bot', 'hashed_key' => ApiKey::hash($key), 'abilities' => ['agents:invoke']]);

    $this->withToken($key)
        ->postJson("/api/public/v1/agents/{$this->agent->id}/sessions/{$session->id}/messages", ['message' => 'Post it.'])
        ->assertOk()
        ->assertJsonPath('data.message.awaiting_approval', true);

    $actionId = $this->withToken($key)
        ->getJson("/api/public/v1/agents/{$this->agent->id}/sessions/{$session->id}/actions")
        ->assertOk()
        ->assertJsonPath('data.actions.0.status', 'pending')
        ->json('data.actions.0.id');

    $this->withToken($key)
        ->postJson("/api/public/v1/agents/{$this->agent->id}/sessions/{$session->id}/actions/decisions", [
            'decisions' => [['action_id' => $actionId, 'decision' => 'approve']],
        ])
        ->assertOk();

    Http::assertSentCount(1);
    expect(AgentAction::query()->sole()->decision_channel)->toBe('api');
});

it('does not let an API caller decide actions outside its conversation or reserved for named approvers', function () {
    $session = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id]);
    $key = ApiKey::generatePlainTextKey();
    $this->workspace->apiKeys()->create(['name' => 'Bot', 'hashed_key' => ApiKey::hash($key), 'abilities' => ['agents:invoke']]);
    $url = "/api/public/v1/agents/{$this->agent->id}/sessions/{$session->id}/actions/decisions";

    $noConversation = AgentAction::factory()->create(['agent_session_id' => null]);
    $named = AgentAction::factory()->forSession($session)->create(['approvers' => ["user:{$this->owner->id}"]]);

    $this->withToken($key)->postJson($url, ['decisions' => [['action_id' => $noConversation->id, 'decision' => 'approve']]])->assertNotFound();
    $this->withToken($key)->postJson($url, ['decisions' => [['action_id' => $named->id, 'decision' => 'approve']]])->assertForbidden();

    expect($noConversation->fresh()->status)->toBe(AgentActionStatus::Pending);
    expect($named->fresh()->status)->toBe(AgentActionStatus::Pending);
});
