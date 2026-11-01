<?php

use App\Ai\Agents\EmbeddedAgent;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\Agents\AutonomyMode;
use App\Enums\Agents\EvalRunStatus;
use App\Enums\Agents\EvalRunTrigger;
use App\Jobs\Agents\RunAgentEvalJob;
use App\Jobs\Agents\RunEvalsOnAgentChangeJob;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentEvalCase;
use App\Models\Agents\AgentEvalRun;
use App\Models\Agents\AgentEvalSuite;
use App\Models\User;
use App\Notifications\Agents\EvalRegressionNotification;
use App\Services\Agents\EvalRunner;
use App\Services\Http\SsrfGuard;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create(['instructions' => 'Be brief.']);
    $this->suite = AgentEvalSuite::factory()->forAgent($this->agent)->create(['name' => 'Basics']);
});

function addEvalCase(AgentEvalSuite $suite, array $assertions, string $input = 'Hi'): AgentEvalCase
{
    return AgentEvalCase::factory()->forSuite($suite)->create(['name' => 'case', 'input' => $input, 'assertions' => $assertions]);
}

it('queues a re-run of the agent\'s suites when its behavior changes', function () {
    Queue::fake();

    $this->agent->update(['instructions' => 'Be thorough.']);

    Queue::assertPushed(RunEvalsOnAgentChangeJob::class, fn (RunEvalsOnAgentChangeJob $job) => $job->agentId === $this->agent->id);
});

it('does not queue a re-run for a cosmetic edit', function () {
    Queue::fake();

    $this->agent->update(['name' => 'Renamed']);

    Queue::assertNotPushed(RunEvalsOnAgentChangeJob::class);
});

it('starts every suite that opted in and has cases, as an agent-change run', function () {
    Queue::fake();
    addEvalCase($this->suite, [['type' => 'contains', 'value' => 'hi']]);
    $optedOut = AgentEvalSuite::factory()->forAgent($this->agent)->create(['run_on_change' => false]);
    addEvalCase($optedOut, [['type' => 'contains', 'value' => 'hi']]);
    AgentEvalSuite::factory()->forAgent($this->agent)->create(['name' => 'Empty']);

    (new RunEvalsOnAgentChangeJob($this->agent->id))->handle(app(EvalRunner::class));

    $run = AgentEvalRun::query()->sole();
    expect($run->agent_eval_suite_id)->toBe($this->suite->id);
    expect($run->trigger)->toBe(EvalRunTrigger::AgentChange);
    Queue::assertPushed(RunAgentEvalJob::class, 1);
});

it('marks a run that passes a smaller share of cases than the previous one as regressed', function () {
    Notification::fake();
    addEvalCase($this->suite, [['type' => 'contains', 'value' => 'refund']]);

    EmbeddedAgent::fake(['We offer a refund.']);
    $first = app(EvalRunner::class)->run($this->suite, $this->owner);

    EmbeddedAgent::fake(['No idea.']);
    $second = app(EvalRunner::class)->run($this->suite, trigger: EvalRunTrigger::AgentChange);

    expect($first->regressed)->toBeFalse();
    expect($second->status)->toBe(EvalRunStatus::Completed);
    expect($second->regressed)->toBeTrue();
    Notification::assertSentTo($this->owner, EvalRegressionNotification::class);
});

it('flags but does not notify a regression in a run someone started by hand', function () {
    Notification::fake();
    addEvalCase($this->suite, [['type' => 'contains', 'value' => 'refund']]);

    EmbeddedAgent::fake(['We offer a refund.']);
    app(EvalRunner::class)->run($this->suite, $this->owner);

    EmbeddedAgent::fake(['No idea.']);
    $second = app(EvalRunner::class)->run($this->suite, $this->owner);

    expect($second->regressed)->toBeTrue();
    Notification::assertNothingSent();
});

it('grades which tools the answer called', function () {
    addEvalCase($this->suite, [
        ['type' => 'tool_called', 'value' => 'remember'],
        ['type' => 'tool_not_called', 'value' => 'forget'],
    ]);
    addEvalCase($this->suite, [['type' => 'tool_called', 'value' => 'remember']]);

    EmbeddedAgent::fake([
        new ToolCall('call-1', 'remember', ['key' => 'name', 'value' => 'Ana']),
        'Noted.',
        'Hello!',
    ]);

    $evalRun = app(EvalRunner::class)->run($this->suite, $this->owner);

    expect($evalRun->passed)->toBe(1);
    expect($evalRun->failed)->toBe(1);
});

it('simulates actions in an agent-change run even when the agent may act freely', function () {
    app()->instance(SsrfGuard::class, new SsrfGuard(fn () => ['203.0.113.10']));
    Http::fake(['hooks.acme.test/*' => Http::response(['ok' => true])]);

    $this->agent->update(['autonomy_mode' => AutonomyMode::Autopilot]);
    $this->agent->toolBindings()->create([
        'node_type' => 'call_api',
        'config' => ['url' => 'https://hooks.acme.test/notify', 'method' => 'POST'],
        'exposed_fields' => ['body'],
    ]);
    addEvalCase($this->suite, [['type' => 'tool_called', 'value' => 'call_api']]);

    EmbeddedAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'Posted.']);

    $evalRun = app(EvalRunner::class)->run($this->suite, trigger: EvalRunTrigger::AgentChange);

    Http::assertNothingSent();
    expect($evalRun->passed)->toBe(1);
    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Simulated);
});
