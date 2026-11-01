<?php

use App\Models\Agents\Agent;
use App\Models\Auth\ApiKey;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

it('lists and shows agents for an invoke-capable key without leaking instructions', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create([
        'name' => 'Support bot',
        'instructions' => 'Internal policy: never mention the lawsuit.',
    ]);

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'Bot key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => ['agents:invoke'],
    ]);

    $index = $this->withToken($plainTextKey)->getJson('/api/public/v1/agents');
    $index->assertOk()->assertJsonPath('data.agents.0.name', 'Support bot');
    expect(json_encode($index->json()))->not->toContain('never mention the lawsuit');

    $this->withToken($plainTextKey)->getJson("/api/public/v1/agents/{$agent->id}")
        ->assertOk()
        ->assertJsonPath('data.agent.id', $agent->id);
});

it('refuses agent discovery to a key without the invoke ability', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'Workflow-only key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => ['workflows:read'],
    ]);

    $this->withToken($plainTextKey)->getJson('/api/public/v1/agents')->assertForbidden();
});
