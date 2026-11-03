<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Agents\SendAgentMessageAction;
use App\Ai\Agents\Workspace\WorkspaceAgent;
use App\Ai\Runtime\StepLimit;
use App\Enums\Agents\SubagentTaskStatus;
use App\Events\Agents\AgentTurnChanged;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\SubagentTask;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create(['settings' => ['max_steps' => 1]]);
    $this->session = app(CreateAgentSessionAction::class)->execute($this->agent, $this->owner);
});

it('marks a reply whose turn ran out of steps while still calling tools', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'remember', ['key' => 'plan', 'value' => 'pro'])]);

    $reply = app(SendAgentMessageAction::class)->execute($this->session, 'Remember my plan, then summarize everything.');

    expect($reply->fresh()->stop_reason)->toBe(StepLimit::REASON);

    $run = Run::query()->where('runnable_id', $this->session->id)->sole();
    expect($run->output['stop_reason'])->toBe(StepLimit::REASON);
    expect((new AgentTurnChanged($this->session, $run))->turn['stop_reason'])->toBe(StepLimit::REASON);
});

it('leaves a reply that finished unmarked', function () {
    WorkspaceAgent::fake(['All done.']);

    $reply = app(SendAgentMessageAction::class)->execute($this->session, 'Hi');

    expect($reply->fresh()->stop_reason)->toBeNull();
    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->output)->not->toHaveKey('stop_reason');
});

it('tells the parent agent when its subagent stopped at the step limit', function () {
    WorkspaceAgent::fake([new ToolCall('call-1', 'remember', ['key' => 'plan', 'value' => 'pro'])]);

    $child = AgentSession::factory()->forAgent($this->agent)->create(['parent_session_id' => $this->session->id]);
    $task = SubagentTask::query()->create([
        'workspace_id' => $this->workspace->id,
        'parent_session_id' => $this->session->id,
        'session_id' => $child->id,
        'agent_id' => $this->agent->id,
        'task' => 'Research the plans',
    ]);
    $task->forceFill(['status' => SubagentTaskStatus::Running])->save();

    app(SendAgentMessageAction::class)->execute($child, 'Research the plans');

    expect($task->fresh()->status)->toBe(SubagentTaskStatus::Completed)
        ->and($task->fresh()->result)->toContain(StepLimit::SUBAGENT_NOTE);
});
