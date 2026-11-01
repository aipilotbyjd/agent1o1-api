<?php

use App\Ai\Agents\SessionEvalJudgeAgent;
use App\Ai\Tools\SubmitEvaluationTool;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvaluationSettings;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Passport\Passport;

it('manually runs and lists session evaluations', function () {
    SessionEvalJudgeAgent::fake([new ToolCall('call_1', SubmitEvaluationTool::NAME, [
        'criteria_results' => [], 'tags' => [], 'data_results' => [], 'sentiment' => null,
        'call_successful' => 'success', 'summary' => 'All good.',
    ])]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    AgentEvaluationSettings::factory()->forAgent($agent)->create(['is_enabled' => true]);
    $session = AgentSession::factory()->forAgent($agent)->create();
    AgentMessage::factory()->forSession($session)->create(['content' => 'Hi']);

    Passport::actingAs($owner);
    $base = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}";

    $this->postJson("{$base}/sessions/{$session->id}/evaluation")
        ->assertOk()
        ->assertJsonPath('data.evaluation.grade', 'pass')
        ->assertJsonPath('data.evaluation.summary', 'All good.');

    $this->getJson("{$base}/session-evaluations")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.grade', 'pass');

    $this->getJson("{$base}/session-evaluations?grade=needs_attention")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('refuses to run an evaluation when the agent has not enabled it', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = AgentSession::factory()->forAgent($agent)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions/{$session->id}/evaluation")
        ->assertStatus(422);
});
