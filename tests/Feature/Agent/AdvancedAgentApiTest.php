<?php

use App\Enums\Role;
use App\Models\Agent;
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

    $this->agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->base = "/api/v1/workspaces/{$this->workspace->id}/agents";
});

test('creating an agent persists advanced settings and exposes them', function () {
    $response = $this->actingAs($this->user, 'api')->postJson($this->base, [
        'name' => 'Advanced Agent',
        'instructions' => 'Be helpful.',
        'planning_enabled' => true,
        'reflection_enabled' => true,
        'memory_semantic_recall' => true,
        'code_execution_enabled' => true,
        'daily_token_budget' => 100000,
        'guardrails' => [
            'output' => ['enabled' => true, 'policy' => 'No PII.', 'block' => true],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.planning_enabled', true)
        ->assertJsonPath('data.reflection_enabled', true)
        ->assertJsonPath('data.code_execution_enabled', true)
        ->assertJsonPath('data.daily_token_budget', 100000)
        ->assertJsonPath('data.guardrails.output.policy', 'No PII.');
});

test('connectors metadata endpoint returns presets', function () {
    $this->actingAs($this->user, 'api')
        ->getJson("{$this->base}/meta/connectors")
        ->assertOk()
        ->assertJsonStructure(['data' => ['connectors' => [['key', 'node_type', 'tool_name', 'tool_description']]]]);
});

test('an agent can be paused and resumed', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("{$this->base}/{$this->agent->id}/pause", ['reason' => 'maintenance'])
        ->assertOk()
        ->assertJsonPath('data.is_paused', true)
        ->assertJsonPath('data.paused_reason', 'maintenance');

    $this->actingAs($this->user, 'api')
        ->postJson("{$this->base}/{$this->agent->id}/resume")
        ->assertOk()
        ->assertJsonPath('data.is_paused', false);
});

test('agent versions are recorded and rollback restores config', function () {
    // Create via the service path so an initial version exists.
    $created = $this->actingAs($this->user, 'api')->postJson($this->base, [
        'name' => 'Versioned Agent',
        'instructions' => 'First version.',
    ])->json('data');

    $agentId = $created['id'];

    // Edit to make a second version.
    $this->actingAs($this->user, 'api')->putJson("{$this->base}/{$agentId}", [
        'instructions' => 'Second version.',
    ])->assertOk();

    $versions = $this->actingAs($this->user, 'api')
        ->getJson("{$this->base}/{$agentId}/versions")
        ->assertOk()
        ->json('data');

    expect(count($versions))->toBeGreaterThanOrEqual(2);

    // Roll back to version 1.
    $this->actingAs($this->user, 'api')
        ->postJson("{$this->base}/{$agentId}/versions/1/rollback")
        ->assertOk()
        ->assertJsonPath('data.instructions', 'First version.');
});

test('eval suites can be created and listed with cases', function () {
    $suite = $this->actingAs($this->user, 'api')->postJson("{$this->base}/{$this->agent->id}/eval-suites", [
        'name' => 'Smoke suite',
        'cases' => [
            [
                'name' => 'greets',
                'input' => 'hello',
                'assertions' => [['type' => 'contains', 'value' => 'hi']],
            ],
        ],
    ])->assertCreated()->json('data');

    expect($suite['name'])->toBe('Smoke suite')
        ->and($suite['cases'])->toHaveCount(1);

    $this->actingAs($this->user, 'api')
        ->getJson("{$this->base}/{$this->agent->id}/eval-suites")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Smoke suite');
});

test('running an empty eval suite is rejected', function () {
    $suite = $this->actingAs($this->user, 'api')->postJson("{$this->base}/{$this->agent->id}/eval-suites", [
        'name' => 'Empty',
    ])->json('data');

    $this->actingAs($this->user, 'api')
        ->postJson("{$this->base}/{$this->agent->id}/eval-suites/{$suite['id']}/run")
        ->assertStatus(422);
});

test('child_agent_ids must reference existing agents', function () {
    $this->actingAs($this->user, 'api')->postJson($this->base, [
        'name' => 'Bad Parent',
        'instructions' => 'x',
        'child_agent_ids' => [Str::uuid()->toString()],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('child_agent_ids.0');
});
