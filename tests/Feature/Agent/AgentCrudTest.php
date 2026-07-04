<?php

use App\Enums\Role;
use App\Models\Agent;
use App\Models\AgentSkill;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);
});

test('member can create an agent with tools', function () {
    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agents", [
            'name' => 'Support Bot',
            'instructions' => 'Help customers politely.',
            'tools' => [
                ['node_type' => 'http_request', 'tool_description' => 'Call an API'],
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Support Bot')
        ->assertJsonPath('data.slug', 'support-bot');

    $agent = Agent::where('name', 'Support Bot')->first();
    expect($agent)->not->toBeNull()
        ->and($agent->toolConfigs)->toHaveCount(1)
        ->and($agent->toolConfigs->first()->tool_name)->toBe('http_request');
});

test('agents are listed for the workspace', function () {
    Agent::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/agents")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('an agent can be shown with its relations', function () {
    $agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$agent->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $agent->id);
});

test('updating the name regenerates the slug', function () {
    $agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->putJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$agent->id}", [
            'name' => 'Renamed Agent',
        ])
        ->assertOk()
        ->assertJsonPath('data.slug', 'renamed-agent');
});

test('an agent can be duplicated and is inactive', function () {
    $agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$agent->id}/duplicate")
        ->assertCreated()
        ->assertJsonPath('data.is_active', false);

    expect(Agent::where('name', "{$agent->name} (Copy)")->exists())->toBeTrue();
});

test('an agent can be deleted', function () {
    $agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$agent->id}")
        ->assertOk();

    expect(Agent::find($agent->id))->toBeNull();
});

test('a skill can be attached and detached', function () {
    $agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
    $skill = AgentSkill::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$agent->id}/skills/attach", [
            'skill_id' => $skill->id,
        ])
        ->assertOk();

    expect($agent->skills()->count())->toBe(1);

    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$agent->id}/skills/{$skill->id}")
        ->assertOk();

    expect($agent->fresh()->skills()->count())->toBe(0);
});

test('non-member cannot access agents', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/agents")
        ->assertForbidden();
});

test('validation fails without name and instructions', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agents", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'instructions']);
});
