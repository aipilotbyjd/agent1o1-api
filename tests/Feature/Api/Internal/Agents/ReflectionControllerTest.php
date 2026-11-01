<?php

use App\Ai\Agents\ReflectionReviewerAgent;
use App\Ai\Tools\SubmitReflectionsTool;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Agents\Reflection;
use App\Models\Agents\ReflectionRun;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Passport\Passport;

/**
 * @return array{0: Agent, 1: User, 2: Workspace}
 */
function reflectionApiAgent(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    return [$agent, $owner, $workspace];
}

it('reads default reflection settings and updates them', function () {
    [$agent, $owner, $workspace] = reflectionApiAgent();
    Passport::actingAs($owner);
    $base = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/reflection-settings";

    $this->getJson($base)
        ->assertOk()
        ->assertJsonPath('data.settings.is_enabled', false)
        ->assertJsonPath('data.settings.apply_behavior', 'review_queue');

    $this->patchJson($base, [
        'is_enabled' => true,
        'apply_behavior' => 'auto_apply',
        'min_chats_threshold' => 10,
    ])
        ->assertOk()
        ->assertJsonPath('data.settings.is_enabled', true)
        ->assertJsonPath('data.settings.apply_behavior', 'auto_apply')
        ->assertJsonPath('data.settings.min_chats_threshold', 10);
});

it('rejects an invalid cron expression', function () {
    [$agent, $owner, $workspace] = reflectionApiAgent();
    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/reflection-settings", [
        'schedule_cron' => 'not a cron expression',
    ])->assertUnprocessable();
});

it('triggers a reflection run on demand and lists it', function () {
    ReflectionReviewerAgent::fake([new ToolCall('call_1', SubmitReflectionsTool::NAME, ['reflections' => []])]);

    [$agent, $owner, $workspace] = reflectionApiAgent();
    $session = AgentSession::factory()->forAgent($agent)->create();
    AgentMessage::factory()->forSession($session)->create();
    Passport::actingAs($owner);
    $base = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/reflection-runs";

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/reflection-settings", ['min_chats_threshold' => 1])
        ->assertOk();

    $runId = $this->postJson($base)
        ->assertCreated()
        ->assertJsonPath('data.run.status', 'completed')
        ->json('data.run.id');

    $this->getJson($base)
        ->assertOk()
        ->assertJsonCount(1, 'data.runs');

    $this->getJson("{$base}/{$runId}")->assertOk()->assertJsonPath('data.run.id', $runId);
});

it('applies and dismisses a reflection', function () {
    ReflectionReviewerAgent::fake([new ToolCall('call_1', SubmitReflectionsTool::NAME, ['reflections' => [
        ['type' => 'instruction_update', 'title' => 'x', 'rationale' => 'r', 'confidence' => 60, 'session_numbers' => [1, 2], 'proposed_prompt' => 'New instructions.'],
        ['type' => 'instruction_update', 'title' => 'y', 'rationale' => 'r', 'confidence' => 60, 'session_numbers' => [1, 2], 'proposed_prompt' => 'Other.'],
    ]])]);

    [$agent, $owner, $workspace] = reflectionApiAgent();
    foreach (AgentSession::factory()->forAgent($agent)->count(2)->create() as $session) {
        AgentMessage::factory()->forSession($session)->create();
    }
    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/reflection-settings", ['min_chats_threshold' => 1]);
    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/reflection-runs")->assertCreated();

    $reflections = $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/reflections")
        ->assertOk()
        ->json('data.reflections');

    expect($reflections)->toHaveCount(2);

    $base = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/reflections";

    $this->postJson("{$base}/{$reflections[0]['id']}/apply")
        ->assertOk()
        ->assertJsonPath('data.reflection.status', 'applied');

    $this->postJson("{$base}/{$reflections[1]['id']}/dismiss")
        ->assertOk()
        ->assertJsonPath('data.reflection.status', 'dismissed');

    expect($agent->fresh()->instructions)->toBe('New instructions.');
});

it('denies a viewer from applying a reflection', function () {
    [$agent, $owner, $workspace] = reflectionApiAgent();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => 'viewer']);

    $run = ReflectionRun::factory()->forAgent($agent)->create();
    $reflection = Reflection::factory()->forRun($run)->create();

    Passport::actingAs($viewer);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/reflections/{$reflection->id}/apply")
        ->assertForbidden();
});
