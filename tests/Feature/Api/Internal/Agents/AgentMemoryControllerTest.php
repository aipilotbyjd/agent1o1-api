<?php

use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('lists an agent\'s memories', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $agent->memories()->create(['key' => 'company_name', 'value' => 'Acme Inc']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/memories");

    $response->assertOk();
    expect($response->json('data.memories'))->toHaveCount(1);
    expect($response->json('data.memories.0.key'))->toBe('company_name');
});

it('creates a memory', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/memories", [
        'key' => 'favorite_color',
        'value' => 'blue',
        'user_id' => $owner->id,
    ]);

    $response->assertCreated();
    expect($response->json('data.memory.key'))->toBe('favorite_color');
    expect($response->json('data.memory.value'))->toBe('blue');
    expect($response->json('data.memory.type'))->toBe('fact');
    expect($agent->memories()->count())->toBe(1);
});

it('updates a memory', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $memory = $agent->memories()->create(['key' => 'favorite_color', 'value' => 'blue']);

    Passport::actingAs($owner);

    $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/memories/{$memory->id}", [
        'value' => 'green',
    ]);

    $response->assertOk();
    expect($response->json('data.memory.value'))->toBe('green');
});

it('deletes a memory', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $memory = $agent->memories()->create(['key' => 'favorite_color', 'value' => 'blue']);

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/memories/{$memory->id}")
        ->assertNoContent();

    expect($agent->memories()->count())->toBe(0);
});

it('404s updating a memory that belongs to a different agent', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $otherAgent = Agent::factory()->forWorkspace($workspace)->create();
    $memory = $otherAgent->memories()->create(['key' => 'favorite_color', 'value' => 'blue']);

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/memories/{$memory->id}", [
        'value' => 'green',
    ])->assertNotFound();
});
