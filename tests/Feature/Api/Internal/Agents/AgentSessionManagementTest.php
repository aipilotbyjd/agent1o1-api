<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Agents\SendAgentMessageAction;
use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Agents\AgentSessionStatus;
use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('pins a session to the agent version it started with', function () {
    WorkspaceAgent::fake(['ok']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'Answer in French.']);

    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);
    expect($session->agent_version_id)->toBe($agent->versions()->sole()->id);

    // The agent is edited mid-conversation...
    $agent->update(['instructions' => 'Answer in German.']);

    // ...but this conversation keeps the behavior it started with.
    expect($session->fresh()->pinnedAgent()->instructions)->toBe('Answer in French.');

    app(SendAgentMessageAction::class)->execute($session->fresh(), 'Bonjour');

    WorkspaceAgent::assertPrompted(fn ($prompt) => str_contains($prompt->agent->instructions(), 'Answer in French.'));
});

it('falls back to the live agent when a session has no pinned version', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'Live instructions.']);

    $session = $agent->sessions()->create(['workspace_id' => $workspace->id]);

    expect($session->agent_version_id)->toBeNull();
    expect($session->pinnedAgent()->instructions)->toBe('Live instructions.');
});

it('renames, archives and deletes a session', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    Passport::actingAs($owner);

    $base = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions/{$session->id}";

    $this->patchJson($base, ['title' => 'Refund questions', 'status' => 'archived'])
        ->assertOk()
        ->assertJsonPath('data.session.title', 'Refund questions');

    expect($session->fresh()->status)->toBe(AgentSessionStatus::Archived);

    $this->deleteJson($base)->assertNoContent();
    expect($agent->sessions()->count())->toBe(0);
});

it('pages through a session transcript', function () {
    WorkspaceAgent::fake(['one', 'two', 'three']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    foreach (['a', 'b', 'c'] as $message) {
        app(SendAgentMessageAction::class)->execute($session, $message);
    }

    Passport::actingAs($owner);

    $response = $this->getJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions/{$session->id}/messages?per_page=2",
    );

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('meta.total'))->toBe(6);
    expect($response->json('data.0.content'))->toBe('a');
});

it('lists only the conversations people started, not ones subagents ran', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);
    $subagentSession = app(CreateAgentSessionAction::class)->execute($agent, $owner);
    $subagentSession->forceFill(['parent_session_id' => $session->id])->save();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions")
        ->assertOk()
        ->assertJsonCount(1, 'data.sessions')
        ->assertJsonPath('data.sessions.0.id', $session->id);
});
