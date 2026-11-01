<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Ai\Agents\WorkspaceAgent;
use App\Ai\Tools\InvokeAgentTool;
use App\Ai\Tools\WaitForSubagentsTool;
use App\Enums\Agents\SubagentTaskStatus;
use App\Enums\RunStatus;
use App\Jobs\Agents\RunSubagentTaskJob;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\SubagentTask;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\ToolRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;

function coordinatorSetup(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $coordinator = Agent::factory()->forWorkspace($workspace)->create(['name' => 'Coordinator']);
    $researcher = Agent::factory()->forWorkspace($workspace)->create(['name' => 'Researcher', 'description' => 'Finds facts.']);
    $coordinator->subagents()->attach($researcher->id);
    $session = app(CreateAgentSessionAction::class)->execute($coordinator, $owner);

    return [$owner, $coordinator->fresh(), $researcher, $session];
}

function subagentTargets(Agent $agent, AgentSession $session): array
{
    $tool = collect(app(ToolRegistry::class)->toolsFor($agent, Run::factory()->create(['workspace_id' => $agent->workspace_id]), $session))
        ->first(fn ($tool) => $tool instanceof InvokeAgentTool);

    return $tool === null ? [] : $tool->schema(new JsonSchemaTypeFactory)['agent']->toArray()['enum'];
}

it('starts the task in the background as a new conversation of the subagent', function () {
    Queue::fake();
    [$owner, , $researcher, $session] = coordinatorSetup();

    $result = json_decode((string) (new InvokeAgentTool($session, ['Researcher' => $researcher]))
        ->handle(new Request(['agent' => 'researcher', 'task' => 'When was Acme founded?'])), true);

    $task = SubagentTask::query()->findOrFail($result['task_id']);
    expect($result)->toMatchArray(['agent' => 'Researcher', 'status' => 'started', 'conversation_id' => $task->session_id]);
    expect($task->status)->toBe(SubagentTaskStatus::Queued);
    expect($task->session->agent_id)->toBe($researcher->id);
    expect($task->session->parent_session_id)->toBe($session->id);
    expect($task->session->user_id)->toBe($owner->id);
    Queue::assertPushedOn('ai-subagent', RunSubagentTaskJob::class, fn ($job) => $job->taskId === $task->id);
});

it('runs a subagent task as a billed chat turn and records its answer', function () {
    WorkspaceAgent::fake(['Acme was founded in 1999.']);
    [$owner, , $researcher, $session] = coordinatorSetup();

    json_decode((string) (new InvokeAgentTool($session, ['Researcher' => $researcher]))
        ->handle(new Request(['agent' => 'Researcher', 'task' => 'When was Acme founded?'])), true);

    $task = SubagentTask::query()->sole();
    expect($task->status)->toBe(SubagentTaskStatus::Completed);
    expect($task->result)->toBe('Acme was founded in 1999.');
    expect($task->session->messages()->pluck('content')->all())->toBe(['When was Acme founded?', 'Acme was founded in 1999.']);

    $run = $task->session->runs()->sole();
    expect($run->trigger_type)->toBe('subagent');
    expect($run->triggered_by)->toBe($owner->id);
});

it('tells a subagent what its siblings are working on', function () {
    Queue::fake();
    [, , $researcher, $session] = coordinatorSetup();
    $tool = new InvokeAgentTool($session, ['Researcher' => $researcher]);
    $tool->handle(new Request(['agent' => 'Researcher', 'task' => 'Research Acme.']));
    $tool->handle(new Request(['agent' => 'Researcher', 'task' => 'Research Globex.']));

    WorkspaceAgent::fake(['done']);
    $first = SubagentTask::query()->where('task', 'Research Acme.')->sole();
    app()->call([new RunSubagentTaskJob($first->id), 'handle']);

    expect($first->session->messages()->oldest()->value('content'))
        ->toContain('Research Acme.')
        ->toContain('- Researcher: Research Globex.');
});

it('refuses to start more than the concurrent limit', function () {
    Queue::fake();
    [, , $researcher, $session] = coordinatorSetup();
    $tool = new InvokeAgentTool($session, ['Researcher' => $researcher]);

    foreach (range(1, InvokeAgentTool::MAX_CONCURRENT) as $i) {
        $tool->handle(new Request(['agent' => 'Researcher', 'task' => "Task {$i}"]));
    }

    expect(json_decode((string) $tool->handle(new Request(['agent' => 'Researcher', 'task' => 'One more'])), true)['error'])
        ->toStartWith('Subagent limit reached.');
});

it('collects every finished result once, and reports what is still running', function () {
    Queue::fake();
    [, , $researcher, $session] = coordinatorSetup();
    $tool = new InvokeAgentTool($session, ['Researcher' => $researcher]);
    $tool->handle(new Request(['agent' => 'Researcher', 'task' => 'Research Acme.']));
    $tool->handle(new Request(['agent' => 'Researcher', 'task' => 'Research Globex.']));
    SubagentTask::query()->where('task', 'Research Acme.')->update(['status' => 'completed', 'result' => 'Acme: 1999.']);

    $wait = new WaitForSubagentsTool($session, waitSeconds: 0, pollMilliseconds: 1);
    $first = json_decode((string) $wait->handle(new Request([])), true);

    expect($first['results'])->toBe([['agent' => 'Researcher', 'task' => 'Research Acme.', 'status' => 'completed', 'answer' => 'Acme: 1999.']]);
    expect($first['still_running'])->toBe(['Researcher: Research Globex.']);
    expect($first['note'])->toBe('1 subagent(s) are still working. Call wait_for_subagents again to collect them before you answer or combine anything.');

    SubagentTask::query()->where('task', 'Research Globex.')->update(['status' => 'failed', 'error' => 'Timed out.']);
    $second = json_decode((string) $wait->handle(new Request([])), true);

    expect($second['results'])->toBe([['agent' => 'Researcher', 'task' => 'Research Globex.', 'status' => 'failed', 'error' => 'Timed out.']]);
    expect($second['still_running'])->toBe([]);
    expect($second)->not->toHaveKey('note');
});

it('refuses a subagent it was not given', function () {
    [, , $researcher, $session] = coordinatorSetup();

    $result = json_decode((string) (new InvokeAgentTool($session, ['Researcher' => $researcher]))
        ->handle(new Request(['agent' => 'Writer', 'task' => 'x'])), true);

    expect($result['error'])->toBe('No subagent named "Writer". Available: Researcher.');
});

it('offers a clone of itself and its attached subagents', function () {
    [, $coordinator, , $session] = coordinatorSetup();

    expect(subagentTargets($coordinator, $session))->toBe(['Me', 'Researcher']);

    $coordinator->update(['allow_self_clone' => false]);
    expect(subagentTargets($coordinator->fresh(), $session))->toBe(['Researcher']);
});

it('does not let a subagent start subagents of its own', function () {
    [$owner, $coordinator, $researcher, $session] = coordinatorSetup();
    $researcher->subagents()->attach(Agent::factory()->forWorkspace($coordinator->workspace)->create()->id);

    $clone = app(CreateAgentSessionAction::class)->execute($coordinator, $owner);
    $clone->forceFill(['parent_session_id' => $session->id])->save();
    $researcherSession = app(CreateAgentSessionAction::class)->execute($researcher, $owner);
    $researcherSession->forceFill(['parent_session_id' => $session->id])->save();

    expect(subagentTargets($coordinator, $clone->fresh()))->toBe([]);
    expect(subagentTargets($researcher->fresh(), $researcherSession->fresh()))->toBe([]);
});

it('gives a subagent whose name clashes with another target a distinct name', function () {
    [, $coordinator, , $session] = coordinatorSetup();
    $coordinator->subagents()->attach([
        Agent::factory()->forWorkspace($coordinator->workspace)->create(['name' => 'researcher'])->id,
        Agent::factory()->forWorkspace($coordinator->workspace)->create(['name' => 'Me'])->id,
    ]);

    expect(subagentTargets($coordinator->fresh(), $session))
        ->toEqualCanonicalizing(['Me', 'Researcher', 'researcher (2)', 'Me (2)']);
});

it('gives up on a subagent that never finished and frees its slot', function () {
    Queue::fake();
    [, , $researcher, $session] = coordinatorSetup();
    $tool = new InvokeAgentTool($session, ['Researcher' => $researcher]);

    foreach (range(1, InvokeAgentTool::MAX_CONCURRENT) as $i) {
        $tool->handle(new Request(['agent' => 'Researcher', 'task' => "Task {$i}"]));
    }

    $this->travel(SubagentTask::STALE_AFTER_MINUTES + 1)->minutes();

    $result = json_decode((string) (new WaitForSubagentsTool($session, waitSeconds: 0, pollMilliseconds: 1))->handle(new Request([])), true);

    expect($result['results'])->toHaveCount(InvokeAgentTool::MAX_CONCURRENT);
    expect($result['results'][0])->toMatchArray(['status' => 'failed', 'error' => 'The subagent never finished.']);
    expect(json_decode((string) $tool->handle(new Request(['agent' => 'Researcher', 'task' => 'One more'])), true))
        ->toHaveKey('task_id');
});

it('fails the subagent turn left running when its job is given up on', function () {
    Queue::fake();
    [, , $researcher, $session] = coordinatorSetup();
    (new InvokeAgentTool($session, ['Researcher' => $researcher]))
        ->handle(new Request(['agent' => 'Researcher', 'task' => 'Research Acme.']));
    $task = SubagentTask::query()->sole();
    $task->forceFill(['status' => SubagentTaskStatus::Running])->save();
    $run = $task->session->runs()->create(['workspace_id' => $task->workspace_id, 'trigger_type' => 'subagent']);
    $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

    (new RunSubagentTaskJob($task->id))->failed(new RuntimeException('Job timed out.'));

    expect($task->fresh()->status)->toBe(SubagentTaskStatus::Failed);
    expect($task->fresh()->error)->toBe('Job timed out.');
    expect($run->fresh()->status)->toBe(RunStatus::Failed);
});

it('tells the model to combine results itself, never through a subagent', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id]);

    expect((string) (new InvokeAgentTool($session, ['Me' => $agent]))->description())
        ->toContain('Never delegate combining, summarising or reviewing other subagents\' results');
});
