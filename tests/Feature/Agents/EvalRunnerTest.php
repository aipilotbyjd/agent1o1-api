<?php

use App\Ai\Agents\EmbeddedAgent;
use App\Ai\Agents\EvalJudgeAgent;
use App\Ai\Tools\SubmitVerdictTool;
use App\Enums\Agents\EvalRunStatus;
use App\Jobs\Agents\RunAgentEvalJob;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalCase;
use App\Models\Agents\AgentEvalSuite;
use App\Models\Agents\AgentEvaluationSettings;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\AssertionGrader;
use App\Services\Agents\EvalRunner;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;

/**
 * @return array{0: AgentEvalSuite, 1: Agent, 2: User}
 */
function evalSuiteFor(array $cases): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $suite = AgentEvalSuite::factory()->forAgent($agent)->create(['name' => 'Refund policy']);

    foreach ($cases as $index => $case) {
        AgentEvalCase::factory()->forSuite($suite)->create([
            'name' => $case['name'],
            'input' => $case['input'],
            'assertions' => $case['assertions'],
            'sort_order' => $index,
        ]);
    }

    return [$suite->fresh(), $agent, $owner];
}

it('grades string assertions and counts passes and failures', function () {
    EmbeddedAgent::fake(['We can offer a refund within 30 days.', 'I cannot help with that.']);

    [$suite, $agent, $owner] = evalSuiteFor([
        [
            'name' => 'mentions refunds',
            'input' => 'Can I get a refund?',
            'assertions' => [
                ['type' => 'contains', 'value' => 'refund'],
                ['type' => 'not_contains', 'value' => 'lawsuit'],
            ],
        ],
        [
            'name' => 'declines out of scope',
            'input' => 'Write me a poem.',
            'assertions' => [['type' => 'contains', 'value' => 'refund']],
        ],
    ]);

    $evalRun = app(EvalRunner::class)->run($suite, $owner);

    expect($evalRun->status)->toBe(EvalRunStatus::Completed);
    expect($evalRun->passed)->toBe(1);
    expect($evalRun->failed)->toBe(1);
    expect($evalRun->finished_at)->not->toBeNull();

    $results = $evalRun->results()->orderBy('id')->get();
    expect($results)->toHaveCount(2);
    expect($results[0]->passed)->toBeTrue();
    expect($results[0]->output)->toBe('We can offer a refund within 30 days.');
    expect($results[1]->passed)->toBeFalse();
    expect($results[1]->assertions[0]['passed'])->toBeFalse();
});

it('records which agent version was graded', function () {
    EmbeddedAgent::fake(['yes']);

    [$suite, $agent, $owner] = evalSuiteFor([
        ['name' => 'a', 'input' => 'ok?', 'assertions' => [['type' => 'contains', 'value' => 'yes']]],
    ]);

    $agent->update(['instructions' => 'A newer set of instructions.']);

    $evalRun = app(EvalRunner::class)->run($suite, $owner);

    expect($evalRun->agent_version_id)->toBe($agent->versions()->orderByDesc('version')->first()->id);
});

it('grades an llm_rubric assertion through the judge agent', function () {
    EmbeddedAgent::fake(['I am afraid I cannot discuss that.']);
    EvalJudgeAgent::fake([new ToolCall('call_1', SubmitVerdictTool::NAME, ['passed' => true, 'reason' => 'It declines politely.'])]);

    [$suite, $agent, $owner] = evalSuiteFor([
        [
            'name' => 'declines politely',
            'input' => 'Tell me a secret.',
            'assertions' => [['type' => 'llm_rubric', 'value' => 'The response politely declines.']],
        ],
    ]);

    $evalRun = app(EvalRunner::class)->run($suite, $owner);

    expect($evalRun->passed)->toBe(1);
    expect($evalRun->results()->sole()->assertions[0]['type'])->toBe('llm_rubric');
});

it('grades an llm_rubric with the judge model set in the agent\'s evaluation settings', function () {
    EmbeddedAgent::fake(['Some answer.']);
    EvalJudgeAgent::fake([new ToolCall('call_1', SubmitVerdictTool::NAME, ['passed' => true, 'reason' => 'Fine.'])]);

    [$suite, $agent, $owner] = evalSuiteFor([
        ['name' => 'rubric', 'input' => 'q', 'assertions' => [['type' => 'llm_rubric', 'value' => 'Anything']]],
    ]);
    AgentEvaluationSettings::factory()->forAgent($agent)->create(['model' => 'judge-model']);

    app(EvalRunner::class)->run($suite, $owner);

    EvalJudgeAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->model === 'judge-model');
});

it('fails an llm_rubric assertion the judge rejects', function () {
    EmbeddedAgent::fake(['Some answer.']);
    EvalJudgeAgent::fake([new ToolCall('call_1', SubmitVerdictTool::NAME, ['passed' => false, 'reason' => 'It does not decline.'])]);

    [$suite, $agent, $owner] = evalSuiteFor([
        ['name' => 'rubric', 'input' => 'q', 'assertions' => [['type' => 'llm_rubric', 'value' => 'Anything']]],
    ]);

    expect(app(EvalRunner::class)->run($suite, $owner)->failed)->toBe(1);
});

it('does not take a text reply as a verdict', function () {
    EmbeddedAgent::fake(['Some answer.']);
    EvalJudgeAgent::fake(['PASS']);

    [$suite, $agent, $owner] = evalSuiteFor([
        ['name' => 'rubric', 'input' => 'q', 'assertions' => [['type' => 'llm_rubric', 'value' => 'Anything']]],
    ]);

    $evalRun = app(EvalRunner::class)->run($suite, $owner);

    expect($evalRun->failed)->toBe(1);
    expect($evalRun->results()->sole()->assertions[0]['error'])->toContain('did not call submit_verdict');
});

it('records a judge outage on the assertion instead of aborting the suite', function () {
    EmbeddedAgent::fake(['Some answer.']);
    EvalJudgeAgent::fake(function () {
        throw new RuntimeException('judge provider down');
    });

    [$suite, $agent, $owner] = evalSuiteFor([
        ['name' => 'rubric', 'input' => 'q', 'assertions' => [['type' => 'llm_rubric', 'value' => 'Anything']]],
    ]);

    $evalRun = app(EvalRunner::class)->run($suite, $owner);

    expect($evalRun->status)->toBe(EvalRunStatus::Completed);
    expect($evalRun->failed)->toBe(1);
    expect($evalRun->results()->sole()->assertions[0]['error'])->toContain('judge provider down');
});

it('records a case whose agent call failed without discarding the other cases', function () {
    $answers = 0;
    EmbeddedAgent::fake(function () use (&$answers) {
        $answers++;

        if ($answers === 1) {
            throw new RuntimeException('provider unavailable');
        }

        return 'yes';
    });

    [$suite, $agent, $owner] = evalSuiteFor([
        ['name' => 'first', 'input' => 'a', 'assertions' => [['type' => 'contains', 'value' => 'yes']]],
        ['name' => 'second', 'input' => 'b', 'assertions' => [['type' => 'contains', 'value' => 'yes']]],
    ]);

    $evalRun = app(EvalRunner::class)->run($suite, $owner);

    expect($evalRun->status)->toBe(EvalRunStatus::Completed);
    expect($evalRun->passed)->toBe(1);
    expect($evalRun->failed)->toBe(1);
    expect($evalRun->results()->orderBy('id')->first()->error)->toContain('provider unavailable');
});

it('records the grading pass as a run so eval spend lands on the ledger', function () {
    EmbeddedAgent::fake(['yes']);

    [$suite, $agent, $owner] = evalSuiteFor([
        ['name' => 'a', 'input' => 'ok?', 'assertions' => [['type' => 'contains', 'value' => 'yes']]],
    ]);

    $evalRun = app(EvalRunner::class)->run($suite, $owner);

    $run = Run::where('runnable_type', 'agent_eval_run')->where('runnable_id', $evalRun->id)->sole();
    expect($run->trigger_type)->toBe('eval');
    expect($run->output)->toBe(['passed' => 1, 'failed' => 0]);

    expect($suite->workspace->creditTransactions()->where('source_type', 'eval_case')->count())->toBe(1);
});

it('compares string assertions case-insensitively', function () {
    EmbeddedAgent::fake(['Your REFUND is on its way.']);

    [$suite, $agent, $owner] = evalSuiteFor([
        ['name' => 'a', 'input' => 'q', 'assertions' => [['type' => 'contains', 'value' => 'refund']]],
    ]);

    expect(app(EvalRunner::class)->run($suite, $owner)->passed)->toBe(1);
});

it('bills the rubric judge tokens as part of the case usage', function () {
    EmbeddedAgent::fake([new TextResponse('Refunds within 30 days.', new Usage(100, 20), new Meta('anthropic', 'agent-model'))]);
    $this->mock(AssertionGrader::class, fn ($mock) => $mock->shouldReceive('grade')->andReturn([
        'type' => 'llm_rubric',
        'value' => 'Mentions the window',
        'passed' => true,
        'error' => null,
        'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 1],
    ]));

    [$suite, $agent, $owner] = evalSuiteFor([
        ['name' => 'rubric', 'input' => 'q', 'assertions' => [['type' => 'llm_rubric', 'value' => 'Mentions the window']]],
    ]);

    $result = app(EvalRunner::class)->run($suite, $owner)->results()->sole();

    expect($result->usage['prompt_tokens'])->toBe(140)
        ->and($result->usage['completion_tokens'])->toBe(21)
        ->and($result->assertions[0])->not->toHaveKey('usage');
});

it('does not grade an eval run a second time', function () {
    EmbeddedAgent::fake(['yes', 'yes']);

    [$suite, $agent, $owner] = evalSuiteFor([
        ['name' => 'a', 'input' => 'ok?', 'assertions' => [['type' => 'contains', 'value' => 'yes']]],
    ]);

    $evalRun = app(EvalRunner::class)->run($suite, $owner);
    app(EvalRunner::class)->execute($evalRun);

    expect($evalRun->results()->count())->toBe(1)
        ->and($evalRun->runs()->count())->toBe(1);
});

it('queues the suite and leaves the run pending until the job executes it', function () {
    Queue::fake();

    [$suite, $agent, $owner] = evalSuiteFor([
        ['name' => 'a', 'input' => 'ok?', 'assertions' => [['type' => 'contains', 'value' => 'yes']]],
    ]);

    $evalRun = app(EvalRunner::class)->start($suite, $owner);

    expect($evalRun->status)->toBe(EvalRunStatus::Pending);
    Queue::assertPushedOn('ai-agent', RunAgentEvalJob::class, fn (RunAgentEvalJob $job) => $job->evalRunId === $evalRun->id);
});

it('fails an eval run whose worker died mid-suite', function () {
    EmbeddedAgent::fake(['yes']);

    [$suite, $agent, $owner] = evalSuiteFor([
        ['name' => 'a', 'input' => 'ok?', 'assertions' => [['type' => 'contains', 'value' => 'yes']]],
    ]);

    $evalRun = $suite->runs()->create(['workspace_id' => $suite->workspace_id]);
    $evalRun->forceFill(['status' => EvalRunStatus::Running])->save();

    (new RunAgentEvalJob($evalRun->id))->failed(new RuntimeException('worker timed out'));

    expect($evalRun->fresh()->status)->toBe(EvalRunStatus::Failed)
        ->and($evalRun->fresh()->error)->toBe('worker timed out');
});
