<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Models\Agents\Agent;
use App\Models\Auth\ApiKey;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

it('starts a session and sends a message via the public api with agents:invoke', function () {
    WorkspaceAgent::fake(['Hello from the public API']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'CI key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => ['agents:invoke'],
    ]);

    $sessionResponse = $this->withToken($plainTextKey)
        ->postJson("/api/public/v1/agents/{$agent->id}/sessions");

    $sessionResponse->assertCreated();
    $sessionId = $sessionResponse->json('data.session.id');

    $messageResponse = $this->withToken($plainTextKey)
        ->postJson("/api/public/v1/agents/{$agent->id}/sessions/{$sessionId}/messages", ['message' => 'hi']);

    $messageResponse->assertOk();
    expect($messageResponse->json('data.message.content'))->toBe('Hello from the public API');
});

it('rejects invoking an agent without the agents:invoke ability', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'Read-only key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => ['workflows:read'],
    ]);

    $this->withToken($plainTextKey)
        ->postJson("/api/public/v1/agents/{$agent->id}/sessions")
        ->assertForbidden();
});

it('404s invoking an agent belonging to a different workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherWorkspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $foreignAgent = Agent::factory()->forWorkspace($otherWorkspace)->create();

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'CI key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => ['*'],
    ]);

    $this->withToken($plainTextKey)
        ->postJson("/api/public/v1/agents/{$foreignAgent->id}/sessions")
        ->assertNotFound();
});
