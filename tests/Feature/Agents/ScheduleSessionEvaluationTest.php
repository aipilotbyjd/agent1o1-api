<?php

use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Jobs\Agents\EvaluateAgentSessionJob;
use App\Listeners\Agents\ScheduleSessionEvaluation;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvaluationSettings;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\SessionEvaluator;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;

/**
 * @return array{0: AgentSession, 1: Run}
 */
function agentSessionRun(bool $enabled = true): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    AgentEvaluationSettings::factory()->forAgent($agent)->create(['is_enabled' => $enabled]);
    $session = AgentSession::factory()->forAgent($agent)->create();

    $run = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'agent_chat']);
    $run->forceFill(['status' => RunStatus::Completed])->save();

    return [$session, $run->fresh()];
}

it('dispatches a delayed evaluation job carrying a fresh debounce token', function () {
    Bus::fake();

    [$session, $run] = agentSessionRun();

    (new ScheduleSessionEvaluation)->handle(new RunCompleted($run));

    Bus::assertDispatched(EvaluateAgentSessionJob::class);
    expect(Cache::get(ScheduleSessionEvaluation::cacheKey($session->id)))->not->toBeNull();
});

it('ignores completed runs whose runnable is not an agent session', function () {
    Bus::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $run = Run::factory()->create(['workspace_id' => $workspace->id]);

    (new ScheduleSessionEvaluation)->handle(new RunCompleted($run));

    Bus::assertNotDispatched(EvaluateAgentSessionJob::class);
});

it('does not evaluate a conversation a subagent ran for another one', function () {
    Bus::fake();

    [$session, $run] = agentSessionRun();
    $parent = AgentSession::factory()->forAgent($session->agent)->create();
    $session->forceFill(['parent_session_id' => $parent->id])->save();

    (new ScheduleSessionEvaluation)->handle(new RunCompleted($run->fresh()));

    Bus::assertNotDispatched(EvaluateAgentSessionJob::class);
});

it('does not schedule an evaluation when the agent has not enabled it', function () {
    Bus::fake();

    [, $run] = agentSessionRun(enabled: false);

    (new ScheduleSessionEvaluation)->handle(new RunCompleted($run));

    Bus::assertNotDispatched(EvaluateAgentSessionJob::class);
});

it('supersedes an earlier scheduled evaluation when a later turn re-debounces', function () {
    [$session, $run] = agentSessionRun();

    (new ScheduleSessionEvaluation)->handle(new RunCompleted($run));
    $staleToken = Cache::get(ScheduleSessionEvaluation::cacheKey($session->id));

    (new ScheduleSessionEvaluation)->handle(new RunCompleted($run->fresh()));
    $freshToken = Cache::get(ScheduleSessionEvaluation::cacheKey($session->id));

    expect($staleToken)->not->toBe($freshToken);

    $this->mock(SessionEvaluator::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('evaluate');
    });

    (new EvaluateAgentSessionJob($session, $staleToken))->handle(app(SessionEvaluator::class));
});

it('evaluates when the job carries the current debounce token', function () {
    [$session, $run] = agentSessionRun();

    (new ScheduleSessionEvaluation)->handle(new RunCompleted($run));
    $token = Cache::get(ScheduleSessionEvaluation::cacheKey($session->id));

    $this->mock(SessionEvaluator::class, function (MockInterface $mock) use ($session): void {
        $mock->shouldReceive('evaluate')->once()->with(Mockery::on(fn ($s) => $s->is($session)));
    });

    (new EvaluateAgentSessionJob($session, $token))->handle(app(SessionEvaluator::class));
});
