<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Models\Agents\Agent;
use App\Models\Agents\SubagentTask;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('adds, lists and removes an agent\'s subagents', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $helper = Agent::factory()->forWorkspace($workspace)->create(['name' => 'Helper']);
    $base = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/subagents";

    Passport::actingAs($owner);

    $this->postJson("{$base}/{$helper->id}")->assertOk()->assertJsonPath('data.subagents.0.name', 'Helper');
    $this->getJson($base)->assertOk()->assertJsonCount(1, 'data.subagents');
    $this->deleteJson("{$base}/{$helper->id}")->assertNoContent();
    expect($agent->subagents()->count())->toBe(0);
});

it('will not make an agent its own subagent or borrow one from another workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $other = app(WorkspaceService::class)->create($owner, ['name' => 'Other']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $foreign = Agent::factory()->forWorkspace($other)->create();
    $base = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/subagents";

    Passport::actingAs($owner);

    $this->postJson("{$base}/{$agent->id}")->assertStatus(422);
    $this->postJson("{$base}/{$foreign->id}")->assertNotFound();
});

it('lists the subagents a conversation has started', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $helper = Agent::factory()->forWorkspace($workspace)->create(['name' => 'Helper']);
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);
    SubagentTask::query()->create([
        'workspace_id' => $workspace->id,
        'parent_session_id' => $session->id,
        'agent_id' => $helper->id,
        'task' => 'Research Acme.',
    ]);

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions/{$session->id}/subagent-tasks")
        ->assertOk()
        ->assertJsonPath('data.tasks.0.agent.name', 'Helper')
        ->assertJsonPath('data.tasks.0.task', 'Research Acme.')
        ->assertJsonPath('data.tasks.0.status', 'queued');
});
