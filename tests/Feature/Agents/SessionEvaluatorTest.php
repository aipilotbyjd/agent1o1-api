<?php

use App\Ai\Agents\SessionEvalJudgeAgent;
use App\Ai\Tools\SubmitEvaluationTool;
use App\Enums\Agents\SessionEvaluationGrade;
use App\Enums\Agents\SessionEvaluationStatus;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvaluationSettings;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\Runs\Run;
use App\Models\User;
use App\Notifications\Agents\SessionEvaluationNotifyNotification;
use App\Services\Agents\SessionEvaluator;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;

/**
 * @return array{0: AgentSession, 1: User}
 */
function sessionFor(array $criteria = [], array $settingsOverrides = []): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    AgentEvaluationSettings::factory()->forAgent($agent)->create([
        'is_enabled' => true,
        'criteria' => $criteria,
        ...$settingsOverrides,
    ]);

    $session = AgentSession::factory()->forAgent($agent)->create();
    AgentMessage::factory()->forSession($session)->create(['content' => 'Can I get a refund?']);
    AgentMessage::factory()->forSession($session)->assistant()->create(['content' => 'Yes, within 30 days.']);

    return [$session->fresh(), $owner];
}

it('grades pass when every criterion succeeds', function () {
    SessionEvalJudgeAgent::fake([new ToolCall('call_1', SubmitEvaluationTool::NAME, [
        'criteria_results' => [['id' => 'c1', 'name' => 'Accuracy', 'result' => 'success', 'rationale' => 'Correct.']],
        'tags' => [], 'data_results' => [], 'sentiment' => 'positive', 'call_successful' => 'success', 'summary' => 'Fine.',
    ])]);

    [$session] = sessionFor([['id' => 'c1', 'name' => 'Accuracy', 'prompt' => 'Correct info', 'type' => 'other', 'priority' => 'flag']]);

    $evaluation = app(SessionEvaluator::class)->evaluate($session);

    expect($evaluation->status)->toBe(SessionEvaluationStatus::Completed);
    expect($evaluation->grade)->toBe(SessionEvaluationGrade::Pass);
});

it('grades needs_review when a flag criterion fails', function () {
    SessionEvalJudgeAgent::fake([new ToolCall('call_1', SubmitEvaluationTool::NAME, [
        'criteria_results' => [['id' => 'c1', 'name' => 'Tone', 'result' => 'failure', 'rationale' => 'Too casual.']],
        'tags' => [], 'data_results' => [], 'sentiment' => 'neutral', 'call_successful' => 'success', 'summary' => 'Off tone.',
    ])]);

    [$session] = sessionFor([['id' => 'c1', 'name' => 'Tone', 'prompt' => 'Professional tone', 'type' => 'voice_tone', 'priority' => 'flag']]);

    $evaluation = app(SessionEvaluator::class)->evaluate($session);

    expect($evaluation->grade)->toBe(SessionEvaluationGrade::NeedsReview);
});

it('grades needs_attention and notifies owners/admins when a notify criterion fails', function () {
    Notification::fake();

    SessionEvalJudgeAgent::fake([new ToolCall('call_1', SubmitEvaluationTool::NAME, [
        'criteria_results' => [['id' => 'c1', 'name' => 'No PII', 'result' => 'failure', 'rationale' => 'Leaked an email.']],
        'tags' => [], 'data_results' => [], 'sentiment' => 'neutral', 'call_successful' => 'success', 'summary' => 'Leaked PII.',
    ])]);

    [$session, $owner] = sessionFor([['id' => 'c1', 'name' => 'No PII', 'prompt' => 'No PII disclosed', 'type' => 'prohibited_action', 'priority' => 'notify']]);

    $evaluation = app(SessionEvaluator::class)->evaluate($session);

    expect($evaluation->grade)->toBe(SessionEvaluationGrade::NeedsAttention);
    Notification::assertSentTo($owner, SessionEvaluationNotifyNotification::class);
});

it('grades needs_review on negative sentiment when configured to affect the grade', function () {
    SessionEvalJudgeAgent::fake([new ToolCall('call_1', SubmitEvaluationTool::NAME, [
        'criteria_results' => [], 'tags' => [], 'data_results' => [],
        'sentiment' => 'negative', 'call_successful' => 'success', 'summary' => 'Frustrated user.',
    ])]);

    [$session] = sessionFor([], ['sentiment_affects_grade' => true]);

    $evaluation = app(SessionEvaluator::class)->evaluate($session);

    expect($evaluation->grade)->toBe(SessionEvaluationGrade::NeedsReview);
});

it('does nothing when evaluations are not enabled for the agent', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = AgentSession::factory()->forAgent($agent)->create();

    expect(app(SessionEvaluator::class)->evaluate($session))->toBeNull();
});

it('replaces the previous evaluation when a session is graded again', function () {
    SessionEvalJudgeAgent::fake([
        new ToolCall('call_1', SubmitEvaluationTool::NAME, ['criteria_results' => [], 'tags' => [], 'data_results' => [], 'sentiment' => null, 'call_successful' => 'success', 'summary' => 'First.']),
        new ToolCall('call_1', SubmitEvaluationTool::NAME, ['criteria_results' => [], 'tags' => [], 'data_results' => [], 'sentiment' => null, 'call_successful' => 'success', 'summary' => 'Second.']),
    ]);

    [$session] = sessionFor();

    $first = app(SessionEvaluator::class)->evaluate($session);
    $second = app(SessionEvaluator::class)->evaluate($session);

    expect($second->id)->toBe($first->id);
    expect($second->summary)->toBe('Second.');
    expect($session->agent->sessionEvaluations()->count())->toBe(1);
});

it('records the grading pass as a run so evaluation spend lands on the ledger', function () {
    SessionEvalJudgeAgent::fake([new ToolCall('call_1', SubmitEvaluationTool::NAME, [
        'criteria_results' => [], 'tags' => [], 'data_results' => [], 'sentiment' => null, 'call_successful' => 'success', 'summary' => 'Fine.',
    ])]);

    [$session] = sessionFor();

    $evaluation = app(SessionEvaluator::class)->evaluate($session);

    $run = Run::where('runnable_type', 'agent_session_evaluation')->where('runnable_id', $evaluation->id)->sole();
    expect($run->trigger_type)->toBe('session_evaluation');

    expect($session->workspace->creditTransactions()->where('source_type', 'session_evaluation')->count())->toBe(1);
});

it('fails the evaluation when the judge does not submit its grading', function () {
    SessionEvalJudgeAgent::fake(['Looks fine to me.']);

    [$session] = sessionFor();

    $evaluation = app(SessionEvaluator::class)->evaluate($session);

    expect($evaluation->status)->toBe(SessionEvaluationStatus::Failed);
    expect($evaluation->error)->toContain('did not call submit_evaluation');
});

it('judges through the agent model catalog chain when the agent is opted in', function () {
    SessionEvalJudgeAgent::fake([new ToolCall('call_1', SubmitEvaluationTool::NAME, [
        'criteria_results' => [], 'tags' => [], 'data_results' => [], 'sentiment' => null, 'call_successful' => 'success', 'summary' => 'Fine.',
    ])]);

    $catalog = ModelCatalog::factory()->create(['slug' => 'claude-3-5-sonnet']);
    ModelRoute::factory()->forCatalog($catalog)->create([
        'execution_provider' => 'anthropic',
        'execution_model_id' => 'claude-3-5-sonnet-latest',
        'priority' => 0,
    ]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['model_catalog_id' => $catalog->id]);
    AgentEvaluationSettings::factory()->forAgent($agent)->create(['is_enabled' => true, 'criteria' => []]);
    $session = AgentSession::factory()->forAgent($agent)->create();
    AgentMessage::factory()->forSession($session)->create(['content' => 'hi']);
    AgentMessage::factory()->forSession($session)->assistant()->create(['content' => 'hello']);

    app(SessionEvaluator::class)->evaluate($session->fresh());

    SessionEvalJudgeAgent::assertPrompted(function (AgentPrompt $prompt) {
        return $prompt->provider->name() === 'anthropic' && $prompt->model === 'claude-3-5-sonnet-latest';
    });
});

it('lets an explicit evaluation-settings model override win over the catalog chain', function () {
    SessionEvalJudgeAgent::fake([new ToolCall('call_1', SubmitEvaluationTool::NAME, [
        'criteria_results' => [], 'tags' => [], 'data_results' => [], 'sentiment' => null, 'call_successful' => 'success', 'summary' => 'Fine.',
    ])]);

    $catalog = ModelCatalog::factory()->create(['slug' => 'claude-3-5-sonnet']);
    ModelRoute::factory()->forCatalog($catalog)->create([
        'execution_provider' => 'anthropic',
        'execution_model_id' => 'claude-3-5-sonnet-latest',
        'priority' => 0,
    ]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['model_catalog_id' => $catalog->id, 'provider' => 'anthropic']);
    AgentEvaluationSettings::factory()->forAgent($agent)->create(['is_enabled' => true, 'criteria' => [], 'model' => 'claude-3-haiku-latest']);
    $session = AgentSession::factory()->forAgent($agent)->create();
    AgentMessage::factory()->forSession($session)->create(['content' => 'hi']);
    AgentMessage::factory()->forSession($session)->assistant()->create(['content' => 'hello']);

    app(SessionEvaluator::class)->evaluate($session->fresh());

    SessionEvalJudgeAgent::assertPrompted(function (AgentPrompt $prompt) {
        return $prompt->provider->name() === 'anthropic' && $prompt->model === 'claude-3-haiku-latest';
    });
});
